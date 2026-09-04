<?php

namespace App\Services\Documents\Types;

use App\Enums\QuestionType;
use App\Models\GeneratedDocument;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A fixed exam/quiz rendered as a print-ready A4 paper (ADR-016): the
 * question paper for handing out in class, or the marking key for the
 * teacher. Only staff who can VIEW the quiz get the paper; the key —
 * answers included — additionally requires the MANAGE ability. Never
 * exposed to takers, never publicly downloadable.
 */
class ExamPaperDocument extends DocumentType
{
    public function view(): string
    {
        return 'exam-paper';
    }

    public function rules(): array
    {
        return ['variant' => ['required', Rule::in(['questions', 'answer_key'])]];
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return Quiz::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        if (! $subject instanceof Quiz) {
            return false;
        }

        $ability = ($params['variant'] ?? 'questions') === 'answer_key' ? 'manage' : 'view';

        return Gate::forUser($user)->allows($ability, $subject);
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var Quiz $subject */
        return ['school_id' => $subject->school_id, 'branch_id' => $subject->branch_id];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var Quiz $subject */
        if (is_array($subject->draw) && $subject->draw !== []) {
            throw ValidationException::withMessages([
                'document' => ['Random-draw exams give every student a different paper — there is no single paper to print.'],
            ]);
        }

        $subject->load([
            'subjectAssignment.subject:id,name',
            'subjectAssignment.section.gradeLevel:id,name',
            'targetAssignments.section:id,name,grade_level_id',
            'subject:id,name', 'gradeLevel:id,name', 'school:id,name,logo_path',
        ]);

        $rows = $subject->quizQuestions()
            ->with('question.parent')
            ->get()
            ->filter(fn (QuizQuestion $qq): bool => $qq->question !== null
                && $qq->question->status === 'published'
                && $qq->question->type !== QuestionType::Group);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'document' => ['Add questions to this exam before printing it.'],
            ]);
        }

        $withKey = ($params['variant'] ?? 'questions') === 'answer_key';

        // Buckets in paper order: declared parts first (index order), then
        // everything unfiled — the same order takers see.
        $parts = $subject->parts ?? [];
        $buckets = [];
        foreach (array_keys($parts) as $index) {
            $buckets[$index] = [];
        }
        $buckets['none'] = [];

        foreach ($rows as $qq) {
            $key = $qq->part_index !== null && isset($parts[$qq->part_index]) ? $qq->part_index : 'none';
            $buckets[$key][] = $qq;
        }

        $number = 0;
        $paperParts = [];

        foreach ($buckets as $key => $bucket) {
            if ($bucket === []) {
                continue;
            }

            $part = $key === 'none' ? null : $parts[$key];

            // A passage/group prints ONCE above its first sub-question.
            $printedGroups = [];

            $paperParts[] = [
                'title' => $part !== null ? (string) ($part['title'] ?? '') : null,
                'instructions' => $part !== null ? $this->pdfHtml($part['instructions'] ?? null) : null,
                'points' => array_sum(array_map(fn (QuizQuestion $qq): float => $qq->effectivePoints(), $bucket)),
                'questions' => array_map(function (QuizQuestion $qq) use (&$number, &$printedGroups, $withKey): array {
                    $row = $this->questionRow($qq, ++$number, $withKey);

                    $parent = $qq->question?->parent;
                    if ($parent !== null && ! in_array($parent->id, $printedGroups, true)) {
                        $printedGroups[] = $parent->id;
                        $row['passage'] = $this->pdfHtml($parent->body['stem'] ?? '');
                    }

                    return $row;
                }, $bucket),
            ];
        }

        $paper = [
            'paper' => [
                'variant' => $withKey ? 'answer_key' : 'questions',
                'title' => $subject->title,
                'kind' => $subject->kind,
                'school_name' => $subject->school?->name,
                'school_logo' => InlineImage::fromStorage($subject->school?->logo_path),
                'subject_name' => $subject->subject?->name ?? $subject->subjectAssignment?->subject?->name,
                'grade_level_name' => $subject->gradeLevel?->name
                    ?? $subject->subjectAssignment?->section?->gradeLevel?->name,
                'section_names' => $subject->targetAssignments->pluck('section.name')->filter()->values()->all(),
                'exam_year_ec' => $subject->exam_year_ec,
                'duration_minutes' => (int) $subject->setting('duration_minutes', 0),
                'total_points' => $rows->sum(fn (QuizQuestion $qq): float => $qq->effectivePoints()),
                'question_count' => $rows->count(),
                'instructions' => $this->pdfHtml($subject->instructions),
                'parts' => $paperParts,
            ],
            'generated_at' => now()->toDateString(),
        ];

        // KaTeX assets load in the print template only when a question
        // actually carries a math marker — plain papers stay lean.
        $paper['paper']['has_math'] = str_contains(json_encode($paper) ?: '', 'data-math');

        return $paper;
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $quiz = $document->subject;

        if (! $quiz instanceof Quiz) {
            return [];
        }

        // Authenticity only — never the questions, never the key.
        return [
            'exam' => $quiz->title,
            'school' => $document->school?->name,
            'issued_on' => $document->created_at?->toDateString(),
        ];
    }

    /**
     * One printable question row.
     *
     * @return array<string, mixed>
     */
    private function questionRow(QuizQuestion $qq, int $number, bool $withKey): array
    {
        $question = $qq->question;
        $body = $question->body ?? [];
        $key = $question->answer_key ?? [];

        $options = collect(is_array($body['options'] ?? null) ? $body['options'] : [])
            ->values()
            ->map(fn (array $option, int $index): array => [
                'id' => (string) ($option['id'] ?? ''),
                'letter' => $this->letter((string) ($option['id'] ?? ''), $index),
                'text' => $this->pdfInline((string) ($option['text'] ?? '')),
            ]);

        // Short options print in two columns like a typeset paper; anything
        // long falls back to one option per line.
        $grid = $options->isNotEmpty() && $options->every(fn (array $o): bool => mb_strlen(strip_tags($o['text'])) <= 40);

        return [
            'number' => $number,
            'type' => $question->type->value,
            'points' => $qq->effectivePoints(),
            'stem' => $this->pdfHtml($body['stem'] ?? ''),
            'options' => $options->all(),
            'options_grid' => $grid,
            'left' => $this->lettered($body['left'] ?? null, numeric: true),
            'right' => $this->lettered($body['right'] ?? null),
            'blanks_count' => count(is_array($key['blanks'] ?? null) ? $key['blanks'] : []),
            ...($withKey ? [
                'answer' => $this->formatKey($question->type, $body, $key),
                'correct_ids' => $this->correctIds($question->type, $key),
                'explanation' => trim(strip_tags((string) ($question->explanation ?? ''))) ?: null,
            ] : []),
        ];
    }

    /**
     * Stored WYSIWYG HTML made self-contained for the PDF renderer: R2
     * images inlined as data URIs, video markers dropped (paper can't play).
     */
    private function pdfHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $html = preg_replace('/<div data-video="[^"]*">\s*<\/div>|<div data-video="[^"]*">/i', '', $html) ?? $html;

        return preg_replace_callback('/<img data-path="([^"]+)">/i', function (array $match): string {
            $inline = InlineImage::fromStorage(html_entity_decode($match[1]));

            return $inline === null ? '' : '<img src="'.$inline.'">';
        }, $html) ?? $html;
    }

    /**
     * @return list<array{id: string, letter: string, text: string}>|null
     */
    private function lettered(mixed $items, bool $numeric = false): ?array
    {
        if (! is_array($items) || $items === []) {
            return null;
        }

        return collect($items)->values()->map(fn (array $item, int $index): array => [
            'id' => (string) ($item['id'] ?? ''),
            'letter' => $numeric ? (string) ($index + 1) : $this->letter((string) ($item['id'] ?? ''), $index),
            'text' => $this->pdfInline((string) ($item['text'] ?? '')),
        ])->all();
    }

    /**
     * A short rich value (option/matching text) made print-safe: sanitized
     * WYSIWYG HTML passes through with images inlined; plain text — which
     * may legitimately contain a raw "<" — is entity-escaped. The template
     * always renders these unescaped.
     */
    private function pdfInline(string $text): string
    {
        if (preg_match('/<[a-z][^>]*>/i', $text) !== 1) {
            return e($text);
        }

        return (string) $this->pdfHtml(QuestionRules::sanitizeInline($text));
    }

    /** Option ids are usually a–d already; anything else letters by position. */
    private function letter(string $id, int $index): string
    {
        return preg_match('/^[a-z]$/i', $id) === 1
            ? strtoupper($id)
            : chr(65 + ($index % 26));
    }

    /** The marking-key line for one question, humanised per type. */
    private function formatKey(QuestionType $type, array $body, array $key): string
    {
        switch ($type) {
            case QuestionType::McqSingle:
                return $this->letterFor($body, (string) ($key['correct'] ?? ''));

            case QuestionType::McqMulti:
                return collect(is_array($key['correct'] ?? null) ? $key['correct'] : [])
                    ->map(fn ($id): string => $this->letterFor($body, (string) $id))
                    ->implode(', ');

            case QuestionType::TrueFalse:
                return ($key['correct'] ?? false) ? 'True' : 'False';

            case QuestionType::ShortAnswer:
                $accepted = is_array($key['accepted'] ?? null) ? $key['accepted'] : [];

                return $accepted === [] ? 'Manual grading' : implode(' / ', $accepted);

            case QuestionType::Numeric:
                $value = (string) ($key['value'] ?? '');
                $tolerance = (float) ($key['tolerance'] ?? 0);

                return $tolerance > 0 ? "{$value} (± {$tolerance})" : $value;

            case QuestionType::FillBlank:
                return collect(is_array($key['blanks'] ?? null) ? $key['blanks'] : [])
                    ->map(fn ($accepted, $index): string => ($index + 1).') '.implode(' / ', (array) $accepted))
                    ->implode('   ');

            case QuestionType::Matching:
                $rightLetters = collect($this->lettered($body['right'] ?? null) ?? [])->keyBy('id');
                $pairs = is_array($key['pairs'] ?? null) ? $key['pairs'] : [];

                // Follow the left column's printed order (1, 2, 3…).
                return collect(is_array($body['left'] ?? null) ? $body['left'] : [])
                    ->values()
                    ->map(function (array $item, int $index) use ($pairs, $rightLetters): string {
                        $rightId = (string) ($pairs[(string) ($item['id'] ?? '')] ?? '');
                        $letter = $rightLetters->get($rightId)['letter'] ?? strtoupper($rightId);

                        return ($index + 1).' → '.$letter;
                    })
                    ->implode(', ');

            case QuestionType::Essay:
                $rubric = trim(strip_tags((string) ($key['rubric'] ?? '')));

                return $rubric !== '' ? 'Rubric: '.$rubric : 'Manual grading';
        }

        return '';
    }

    /**
     * The correct option ids (for highlighting on the key variant).
     *
     * @return list<string>
     */
    private function correctIds(QuestionType $type, array $key): array
    {
        return match ($type) {
            QuestionType::McqSingle => [(string) ($key['correct'] ?? '')],
            QuestionType::McqMulti => array_map(strval(...), is_array($key['correct'] ?? null) ? $key['correct'] : []),
            default => [],
        };
    }

    private function letterFor(array $body, string $id): string
    {
        $options = collect(is_array($body['options'] ?? null) ? $body['options'] : [])->values();
        $index = $options->search(fn (array $option): bool => (string) ($option['id'] ?? '') === $id);

        return $index === false ? strtoupper($id) : $this->letter($id, (int) $index);
    }
}
