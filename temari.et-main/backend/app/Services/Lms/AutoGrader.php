<?php

namespace App\Services\Lms;

use App\Enums\QuestionType;
use App\Models\Question;

/**
 * Server-side scoring for one answer (ADR-016). Returns the earned points,
 * or NULL when the question needs a human (essays always; short answers
 * only when the key lists no accepted strings). Selection types earn
 * partial credit; wrong picks never take a score below zero.
 *
 * Pluggable by design: the AI-grading phase adds another grader in front of
 * the manual queue without touching this class.
 */
class AutoGrader
{
    /** @param float $points the question's worth inside this quiz */
    public function grade(Question $question, mixed $answer, float $points): ?float
    {
        if ($answer === null) {
            return 0.0;
        }

        return match ($question->type) {
            QuestionType::McqSingle => $this->gradeSingle($question, $answer, $points),
            QuestionType::TrueFalse => $this->gradeTrueFalse($question, $answer, $points),
            QuestionType::McqMulti => $this->gradeMulti($question, $answer, $points),
            QuestionType::ShortAnswer => $this->gradeShortAnswer($question, $answer, $points),
            QuestionType::Numeric => $this->gradeNumeric($question, $answer, $points),
            QuestionType::FillBlank => $this->gradeFillBlank($question, $answer, $points),
            QuestionType::Matching => $this->gradeMatching($question, $answer, $points),
            QuestionType::Essay => null,
        };
    }

    private function gradeSingle(Question $question, mixed $answer, float $points): float
    {
        $correct = data_get($question->answer_key, 'correct');

        return (string) $answer === (string) $correct ? $points : 0.0;
    }

    private function gradeTrueFalse(Question $question, mixed $answer, float $points): float
    {
        $correct = (bool) data_get($question->answer_key, 'correct');

        return filter_var($answer, FILTER_VALIDATE_BOOL) === $correct ? $points : 0.0;
    }

    /**
     * Partial credit: each correct pick earns, each wrong pick cancels one
     * correct pick, floored at zero — guessing everything scores nothing.
     */
    private function gradeMulti(Question $question, mixed $answer, float $points): float
    {
        $correct = collect(data_get($question->answer_key, 'correct', []))->map(fn ($v) => (string) $v);
        $picked = collect(is_array($answer) ? $answer : [$answer])->map(fn ($v) => (string) $v)->unique();

        if ($correct->isEmpty()) {
            return 0.0;
        }

        $hits = $picked->intersect($correct)->count();
        $misses = $picked->diff($correct)->count();

        return round(max(0, $hits - $misses) / $correct->count() * $points, 2);
    }

    /** Case/whitespace-insensitive match against the accepted strings. */
    private function gradeShortAnswer(Question $question, mixed $answer, float $points): ?float
    {
        $accepted = data_get($question->answer_key, 'accepted', []);

        if (! is_array($accepted) || $accepted === []) {
            return null; // no key — a human decides
        }

        $given = $this->normalize((string) $answer);

        foreach ($accepted as $candidate) {
            if ($given === $this->normalize((string) $candidate)) {
                return $points;
            }
        }

        return 0.0;
    }

    private function gradeNumeric(Question $question, mixed $answer, float $points): float
    {
        if (! is_numeric($answer)) {
            return 0.0;
        }

        $value = (float) data_get($question->answer_key, 'value');
        $tolerance = (float) data_get($question->answer_key, 'tolerance', 0);

        return abs((float) $answer - $value) <= $tolerance + 1e-9 ? $points : 0.0;
    }

    /** Partial credit per blank; each blank has its own accepted strings. */
    private function gradeFillBlank(Question $question, mixed $answer, float $points): float
    {
        $blanks = data_get($question->answer_key, 'blanks', []);
        $given = is_array($answer) ? array_values($answer) : [$answer];

        if (! is_array($blanks) || $blanks === []) {
            return 0.0;
        }

        $earned = 0;
        foreach (array_values($blanks) as $i => $accepted) {
            $entry = $this->normalize((string) ($given[$i] ?? ''));
            foreach ((array) $accepted as $candidate) {
                if ($entry !== '' && $entry === $this->normalize((string) $candidate)) {
                    $earned++;
                    break;
                }
            }
        }

        return round($earned / count($blanks) * $points, 2);
    }

    /** Partial credit per correctly matched pair. */
    private function gradeMatching(Question $question, mixed $answer, float $points): float
    {
        $pairs = data_get($question->answer_key, 'pairs', []);

        if (! is_array($pairs) || $pairs === [] || ! is_array($answer)) {
            return 0.0;
        }

        $earned = 0;
        foreach ($pairs as $left => $right) {
            if (isset($answer[$left]) && (string) $answer[$left] === (string) $right) {
                $earned++;
            }
        }

        return round($earned / count($pairs) * $points, 2);
    }

    /** Trim, collapse whitespace, casefold — Amharic text passes through mb-safely. */
    private function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
