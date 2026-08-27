<?php

namespace App\Services\Lms;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The exam session engine (ADR-016). Everything that matters is decided on
 * the server: the paper (drawn + shuffled at start, frozen on the attempt),
 * the clock (`deadline_at`), the scores (AutoGrader on submit). The client
 * only ever renders and autosaves. Integrity events accumulate as flags for
 * human review — never auto-fail.
 */
class QuizAttemptService
{
    /** Seconds of grace past the deadline before answers are refused. */
    public const GRACE_SECONDS = 30;

    public function __construct(
        private readonly AutoGrader $grader,
        private readonly GradebookSync $gradebook,
    ) {}

    /**
     * Start (or resume) an attempt. Resuming returns the live attempt
     * untouched — the paper and clock never re-roll mid-sitting.
     */
    public function start(Quiz $quiz, User $user, ?string $accessCode = null, ?string $tokenId = null): QuizAttempt
    {
        if ($quiz->status !== QuizStatus::Published || ! $quiz->windowOpen()) {
            throw ValidationException::withMessages(['quiz' => ['This exam is not open right now.']]);
        }

        if ($quiz->requiresAccessCode() && ! Hash::check((string) $accessCode, $quiz->access_code_hash)) {
            throw ValidationException::withMessages(['access_code' => ['The access code is incorrect.']]);
        }

        [$student, $enrollment] = $this->resolveTaker($quiz, $user);

        // Sweep an expired live sitting into the graded pile first.
        $live = $quiz->attempts()
            ->where('user_id', $user->id)
            ->where('status', QuizAttemptStatus::InProgress->value)
            ->latest('started_at')
            ->first();

        if ($live !== null) {
            if (! $live->isExpired()) {
                if ($tokenId !== null && $live->token_hash !== null && ! hash_equals($live->token_hash, hash('sha256', $tokenId))) {
                    $this->logEvent($live, 'device_change');
                }

                return $live;
            }

            $this->submit($live, expired: true);
        }

        // Invalidated sittings give the seat back (don't count against the
        // quota) but their rows stay forever — so the new attempt's number
        // must continue from the highest EVER used, invalidated included.
        $taken = $quiz->attempts()
            ->where('user_id', $user->id)
            ->where('status', '!=', QuizAttemptStatus::Invalidated->value)
            ->count();

        $allowed = (int) $quiz->setting('attempts_allowed', 1);
        if ($allowed > 0 && $taken >= $allowed) {
            throw ValidationException::withMessages(['quiz' => ['You have used all your attempts for this exam.']]);
        }

        $lastNumber = (int) $quiz->attempts()
            ->where('user_id', $user->id)
            ->max('attempt_number');

        $seed = random_int(1, mt_getrandmax());
        $paper = $this->resolvePaper($quiz, $seed);

        if ($paper->isEmpty()) {
            throw ValidationException::withMessages(['quiz' => ['This exam has no questions yet.']]);
        }

        $deadline = null;
        $duration = (int) $quiz->setting('duration_minutes', 0);
        if ($duration > 0) {
            $deadline = now()->addMinutes($duration);
        }
        $closesAt = $quiz->setting('closes_at');
        if ($closesAt !== null) {
            $hardStop = Carbon::parse($closesAt);
            $deadline = $deadline === null ? $hardStop : $deadline->min($hardStop);
        }

        return $quiz->attempts()->create([
            'user_id' => $user->id,
            'student_id' => $student?->id,
            'student_enrollment_id' => $enrollment?->id,
            'attempt_number' => $lastNumber + 1,
            'status' => QuizAttemptStatus::InProgress->value,
            'started_at' => now(),
            'deadline_at' => $deadline,
            'seed' => $seed,
            'question_ids' => $paper->values()->all(),
            'max_score' => $paper->sum('points'),
            'token_hash' => $tokenId !== null ? hash('sha256', $tokenId) : null,
        ]);
    }

    /**
     * The attempt's paper for the player: questions in attempt order,
     * options shuffled deterministically, answer keys STRIPPED, saved
     * answers included so resume paints exactly where the taker left off.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paper(QuizAttempt $attempt): array
    {
        $entries = collect($attempt->question_ids);
        $questions = Question::query()
            ->whereIn('id', $entries->pluck('id'))
            ->get()
            ->keyBy('id');

        $answers = $attempt->answers()->get()->keyBy('question_id');
        $shuffleOptions = (bool) $attempt->quiz->setting('shuffle_options', false);

        return $entries->map(function (array $entry, int $index) use ($questions, $answers, $attempt, $shuffleOptions): ?array {
            /** @var ?Question $question */
            $question = $questions->get($entry['id']);

            if ($question === null) {
                return null;
            }

            $body = $question->presentBody();

            if ($shuffleOptions && isset($body['options']) && is_array($body['options'])) {
                $body['options'] = $this->seededShuffle($body['options'], $attempt->seed + (int) $question->id);
            }

            // Takers need the blank COUNT to render inputs — never the key.
            if ($question->type === QuestionType::FillBlank) {
                $body['blanks_count'] = count(data_get($question->answer_key, 'blanks', []));
            }

            return [
                'question_id' => $question->id,
                'number' => $index + 1,
                'part' => $entry['part'] ?? null,
                'group_id' => isset($entry['group']) ? (int) $entry['group'] : ($question->parent_id !== null ? (int) $question->parent_id : null),
                'type' => $question->type->value,
                'points' => (float) $entry['points'],
                'body' => $body,
                'answer' => $answers->get($question->id)?->answer,
            ];
        })->filter()->values()->all();
    }

    /**
     * The passages/introductions of every question group referenced by a
     * paper, keyed by group id — sent once, rendered above each member.
     * Groups carry no answer key, so this is always taker-safe.
     *
     * @param  array<int, array<string, mixed>>  $paperRows
     * @return array<int, array{id: int, stem: string, attachments?: array<int, mixed>}>
     */
    public function groupStems(array $paperRows): array
    {
        $ids = collect($paperRows)->pluck('group_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Question::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (Question $group): array {
                $body = $group->presentBody();

                return [$group->id => [
                    'id' => $group->id,
                    'stem' => (string) ($body['stem'] ?? ''),
                    ...(isset($body['attachments']) ? ['attachments' => $body['attachments']] : []),
                ]];
            })
            ->all();
    }

    /** Autosave one answer. Refuses once the grace window has passed. */
    public function saveAnswer(QuizAttempt $attempt, int $questionId, mixed $answer): QuizAttemptAnswer
    {
        $this->assertAcceptsAnswers($attempt);

        $valid = collect($attempt->question_ids)->contains(fn (array $e): bool => (int) $e['id'] === $questionId);
        if (! $valid) {
            throw ValidationException::withMessages(['question_id' => ['This question is not part of your exam.']]);
        }

        return QuizAttemptAnswer::query()->updateOrCreate(
            ['quiz_attempt_id' => $attempt->id, 'question_id' => $questionId],
            ['answer' => $answer, 'answered_at' => now()],
        );
    }

    /**
     * Finalize: auto-grade every question, freeze the score, hand fully
     * machine-graded attempts straight to the gradebook. Manual questions
     * park the attempt in `submitted` + pending_manual for the teacher.
     */
    public function submit(QuizAttempt $attempt, bool $expired = false): QuizAttempt
    {
        if ($attempt->status->isFinal()) {
            return $attempt;
        }

        if ($expired) {
            $this->logEvent($attempt, 'auto_submitted', flag: false);
        }

        DB::transaction(function () use ($attempt): void {
            $questions = Question::query()
                ->whereIn('id', collect($attempt->question_ids)->pluck('id'))
                ->get()
                ->keyBy('id');

            $answers = $attempt->answers()->get()->keyBy('question_id');

            $total = 0.0;
            $pendingManual = false;

            foreach ($attempt->question_ids as $entry) {
                /** @var ?Question $question */
                $question = $questions->get($entry['id']);
                if ($question === null) {
                    continue;
                }

                $row = $answers->get($question->id);
                $auto = $this->grader->grade($question, $row?->answer, (float) $entry['points']);

                if ($auto === null) {
                    // Needs a human; unanswered manual questions score zero.
                    if ($row === null || $row->answer === null || $row->answer === '') {
                        $auto = 0.0;
                    } else {
                        $pendingManual = true;
                    }
                }

                if ($row === null && $auto !== null) {
                    $row = $attempt->answers()->create(['question_id' => $question->id]);
                }

                $row?->forceFill(['auto_score' => $auto])->save();
                $total += $auto ?? 0.0;
            }

            $attempt->forceFill([
                'status' => $pendingManual ? QuizAttemptStatus::Submitted->value : QuizAttemptStatus::Graded->value,
                'submitted_at' => now(),
                'graded_at' => $pendingManual ? null : now(),
                'score' => round($total, 2),
                'pending_manual' => $pendingManual,
            ])->save();
        });

        $attempt->refresh();

        if ($attempt->status === QuizAttemptStatus::Graded) {
            $this->gradebook->syncQuiz($attempt->quiz, $attempt->student_id);
        }

        return $attempt;
    }

    /**
     * Recompute the total after manual grading; graduates the attempt to
     * `graded` when nothing is left pending and pushes the gradebook.
     */
    public function refreshScore(QuizAttempt $attempt): QuizAttempt
    {
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $total = 0.0;
        $pending = false;

        foreach ($attempt->question_ids as $entry) {
            $row = $answers->get($entry['id']);
            $score = $row?->effectiveScore();

            if ($score === null) {
                $pending = true;

                continue;
            }

            $total += min($score, (float) $entry['points']);
        }

        $attempt->forceFill([
            'score' => round($total, 2),
            'pending_manual' => $pending,
            'status' => $pending ? QuizAttemptStatus::Submitted->value : QuizAttemptStatus::Graded->value,
            'graded_at' => $pending ? null : now(),
        ])->save();

        if (! $pending) {
            $this->gradebook->syncQuiz($attempt->quiz, $attempt->student_id);
        }

        return $attempt;
    }

    /** Append an integrity event (blur, paste, device change…) for review. */
    public function logEvent(QuizAttempt $attempt, string $type, array $meta = [], bool $flag = true): void
    {
        $log = $attempt->integrity_log ?? [];
        $log[] = ['type' => $type, 'at' => now()->toIso8601String(), ...($meta === [] ? [] : ['meta' => $meta])];

        $attempt->forceFill([
            'integrity_log' => $log,
            'flag_count' => $attempt->flag_count + ($flag ? 1 : 0),
        ])->save();
    }

    /**
     * Who is sitting this quiz. Class quizzes are for the section's own
     * (pending or active) enrollees only; platform mocks admit any user.
     *
     * @return array{0: ?Student, 1: ?StudentEnrollment}
     */
    private function resolveTaker(Quiz $quiz, User $user): array
    {
        $student = Student::query()->where('user_id', $user->id)->first();

        if ($quiz->is_platform) {
            return [$student, null];
        }

        // The exam may run across several sections — any target admits.
        $sectionIds = $quiz->targetAssignments()->pluck('section_id')->filter();

        $enrollment = $student === null || $sectionIds->isEmpty() ? null : StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('section_id', $sectionIds)
            ->whereIn('status', [EnrollmentStatus::Active->value])
            ->first();

        if ($enrollment === null) {
            throw ValidationException::withMessages(['quiz' => ['This exam belongs to a class you are not enrolled in.']]);
        }

        return [$student, $enrollment];
    }

    /**
     * A one-off SAMPLE paper for staff preview — same resolution as a real
     * sitting (fixed picks, or a fresh draw), nothing persisted.
     *
     * @return Collection<int, array{id: int, points: float, part: ?int, group: ?int}>
     */
    public function samplePaper(Quiz $quiz): Collection
    {
        return $this->resolvePaper($quiz, random_int(1, mt_getrandmax()));
    }

    /**
     * Resolve this sitting's paper: fixed picks in their set order, or a
     * fresh random draw per attempt — neighbours get different papers.
     * Papers with parts keep their part order — shuffling only ever moves a
     * question WITHIN its part, exactly like a printed paper.
     *
     * @return Collection<int, array{id: int, points: float, part: ?int}>
     */
    private function resolvePaper(Quiz $quiz, int $seed): Collection
    {
        if (! is_array($quiz->draw) || $quiz->draw === []) {
            $paper = $quiz->quizQuestions()->with('question:id,points,status,parent_id,type')->get()
                ->filter(fn ($qq): bool => $qq->question !== null
                    && $qq->question->status === 'published'
                    && $qq->question->type !== QuestionType::Group)
                ->map(fn ($qq): array => [
                    'id' => (int) $qq->question_id,
                    'points' => $qq->effectivePoints(),
                    'part' => $qq->part_index,
                    'group' => $qq->question->parent_id !== null ? (int) $qq->question->parent_id : null,
                ]);

            if ((bool) $quiz->setting('shuffle_questions', false)) {
                // Parts keep their stored order (groupBy preserves first
                // appearance); each part shuffles with its own sub-seed.
                // Within a part, sibling sub-questions of one question group
                // (a passage, a matching set) travel as ONE unit — shuffling
                // reorders whole units, never tears a group apart.
                return $paper
                    ->groupBy(fn (array $entry): string => $entry['part'] === null ? 'none' : (string) $entry['part'])
                    ->flatMap(function (Collection $group, int|string $key) use ($seed): Collection {
                        $units = $group
                            ->groupBy(fn (array $entry): string => $entry['group'] === null ? 'q'.$entry['id'] : 'g'.$entry['group'])
                            ->values()
                            ->map(fn (Collection $unit): array => $unit->values()->all())
                            ->all();

                        return collect($this->seededShuffle($units, $seed + crc32((string) $key)))->flatten(1);
                    })
                    ->values();
            }

            return $paper->values();
        }

        $paper = collect();

        foreach ($quiz->draw as $rule) {
            // Random draws pick standalone questions only — a passage group
            // is one pedagogical unit and can't be split by a lottery.
            $picked = Question::query()
                ->where('question_bank_id', (int) $rule['question_bank_id'])
                ->where('status', 'published')
                ->whereNull('parent_id')
                ->where('type', '!=', QuestionType::Group->value)
                ->when(! empty($rule['difficulty']), fn ($q) => $q->where('difficulty', $rule['difficulty']))
                ->when(! empty($rule['tags']), function ($q) use ($rule): void {
                    foreach ((array) $rule['tags'] as $tag) {
                        $q->whereJsonContains('tags', $tag);
                    }
                })
                ->whereNotIn('id', $paper->pluck('id'))
                ->inRandomOrder()
                ->limit((int) $rule['count'])
                ->get(['id', 'points']);

            foreach ($picked as $question) {
                $paper->push(['id' => (int) $question->id, 'points' => (float) $question->points, 'part' => null, 'group' => null]);
            }
        }

        // Draw order is already random; a fixed-seed shuffle keeps resumes stable.
        return collect($this->seededShuffle($paper->all(), $seed));
    }

    private function assertAcceptsAnswers(QuizAttempt $attempt): void
    {
        if ($attempt->status->isFinal()) {
            throw ValidationException::withMessages(['attempt' => ['This attempt is already submitted.']]);
        }

        if ($attempt->deadline_at !== null && now()->greaterThan($attempt->deadline_at->copy()->addSeconds(self::GRACE_SECONDS))) {
            $this->submit($attempt, expired: true);

            throw ValidationException::withMessages(['attempt' => ['Time is up — your exam was submitted automatically.']]);
        }
    }

    /**
     * Deterministic Fisher–Yates so every fetch of the same attempt renders
     * the same order.
     *
     * @template T
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    private function seededShuffle(array $items, int $seed): array
    {
        mt_srand($seed);

        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        mt_srand();

        return array_values($items);
    }
}
