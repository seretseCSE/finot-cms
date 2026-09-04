<?php

namespace App\Ai\Tools\Family;

use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Exam/quiz history — and, for one attempt, the per-question review that
 * powers "explain my mistake". Follows the LMS reveal rules EXACTLY
 * (ADR-016): results only when the quiz's results_policy allows, the answer
 * key only when reveal_answers is on, never for an in-progress attempt.
 * The tutor explains the CONCEPT behind a miss; it never leaks a hidden key.
 */
class StudentExamHistoryTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get quiz/exam history (scores per attempt), or pass attempt_id for a per-question review of one finished attempt — the student\'s answer vs the correct answer where the teacher has released it. Use for "explain my mistake", "why did I fail", or progress questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$student, $link, $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        if (! $this->linkAllows($link, 'can_view_grades')) {
            return $this->deny('Your guardian link does not permit viewing this student\'s exam results.');
        }

        // The attempt engine keys platform attempts by user_id and class
        // attempts by student_id — cover both for the student's own account.
        $ownerFilter = function ($query) use ($student): void {
            $query->where('student_id', $student->id)
                ->orWhere('user_id', $student->user_id ?? 0);
        };

        $attemptId = $request->integer('attempt_id') ?: null;

        if ($attemptId !== null) {
            $attempt = QuizAttempt::query()
                ->where('id', $attemptId)
                ->where($ownerFilter)
                ->with('quiz')
                ->first();

            if ($attempt === null) {
                return $this->deny('No such attempt for this student.');
            }

            return $this->attemptReview($attempt);
        }

        $attempts = QuizAttempt::query()
            ->where($ownerFilter)
            ->where('status', '!=', QuizAttemptStatus::InProgress->value)
            ->with(['quiz:id,title,kind,is_platform,subject_id,settings,status', 'quiz.subject:id,name'])
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get()
            ->map(fn (QuizAttempt $attempt): array => [
                'attempt_id' => $attempt->id,
                'quiz' => $attempt->quiz?->title,
                'subject' => $attempt->quiz?->subject?->name,
                'kind' => $attempt->quiz?->kind,
                'score' => $this->resultsVisible($attempt->quiz, $attempt) && $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => (float) $attempt->max_score,
                'status' => $attempt->status->value,
                'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
            ]);

        return $this->ok(['student' => $student->full_name, 'attempts' => $attempts]);
    }

    private function attemptReview(QuizAttempt $attempt): string
    {
        $quiz = $attempt->quiz;

        if ($attempt->status === QuizAttemptStatus::InProgress) {
            return $this->deny('This exam is still in progress — reviewing it now would be cheating. Encourage finishing it honestly first.');
        }

        if (! $this->resultsVisible($quiz, $attempt)) {
            return $this->deny('Results for this exam have not been released yet.');
        }

        $reveal = (bool) $quiz->setting('reveal_answers', false);
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $questions = Question::query()
            ->whereIn('id', collect($attempt->question_ids)->pluck('id'))
            ->get()
            ->keyBy('id');

        $rows = collect($attempt->question_ids)
            ->map(function (array $entry, int $index) use ($questions, $answers, $reveal): ?array {
                $question = $questions->get($entry['id']);
                if ($question === null) {
                    return null;
                }
                $answer = $answers->get($question->id);
                $earned = $answer?->effectiveScore();

                return [
                    'number' => $index + 1,
                    'question' => strip_tags((string) ($question->presentBody()['stem'] ?? '')),
                    'points' => (float) $entry['points'],
                    'student_answer' => $answer?->answer,
                    'earned' => $earned,
                    'correct' => $earned !== null ? $earned >= (float) $entry['points'] : null,
                    ...($reveal ? [
                        'answer_key' => $question->answer_key,
                        'explanation' => $question->explanation,
                    ] : []),
                ];
            })
            ->filter()
            // Focus the context on misses first, cap the payload.
            ->sortBy(fn (array $row) => $row['correct'] === false ? 0 : 1)
            ->take(25)
            ->values();

        return $this->ok([
            'quiz' => $quiz->title,
            'score' => $attempt->score !== null ? (float) $attempt->score : null,
            'max_score' => (float) $attempt->max_score,
            'answer_key_released' => $reveal,
            'questions' => $rows,
        ]);
    }

    private function resultsVisible(?Quiz $quiz, QuizAttempt $attempt): bool
    {
        if ($quiz === null || $attempt->status === QuizAttemptStatus::InProgress) {
            return false;
        }

        return match ((string) $quiz->setting('results_policy', 'immediately')) {
            'after_close' => $quiz->status === QuizStatus::Closed || ! $quiz->windowOpen(),
            'manual' => (bool) $quiz->setting('results_released', false),
            default => true,
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('Parent lane only: the child to look at (from my_children). Omit in the student lane.'),
            'attempt_id' => $schema->integer()->description('Review one finished attempt in detail (per-question).'),
        ];
    }
}
