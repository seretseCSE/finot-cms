<?php

namespace App\Services\Lms;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Validation\ValidationException;

/**
 * The ONE publish/close path for quizzes/exams (ADR-016) — shared by the
 * exam studio (QuizController) and the AI exam builder so the rules can
 * never drift: publishing freezes the paper's worth (published, non-group
 * questions only), refuses empty/draft-only papers with a user-safe reason,
 * verifies random-draw rules are satisfiable, and announces the FIRST
 * publish of a class exam to the targeted sections' students (in-app,
 * queued). Platform mocks are open-lane content — no fan-out.
 */
class QuizPublisher
{
    /**
     * Publish: freeze the paper's worth and open the availability window.
     *
     * @throws ValidationException when the paper cannot go live
     */
    public function publish(Quiz $quiz): Quiz
    {
        // A window that already closed can never be sat — surface it here so
        // every publish lane (studio, AI chat) refuses with the same reason.
        $closesAt = $quiz->settings['closes_at'] ?? null;
        if ($closesAt !== null && now()->greaterThan($closesAt)) {
            throw ValidationException::withMessages(['settings.closes_at' => [
                'The closing time has already passed — move the window forward, then publish.',
            ]]);
        }

        $picked = $quiz->quizQuestions()->with('question:id,points,status,type')->get()
            ->filter(fn ($qq): bool => $qq->question !== null
                && $qq->question->type !== QuestionType::Group);

        $fixedPoints = $picked
            ->filter(fn ($qq): bool => $qq->question?->status === 'published')
            ->sum(fn ($qq): float => $qq->effectivePoints());

        $hasDraw = is_array($quiz->draw) && $quiz->draw !== [];

        if ($fixedPoints <= 0 && ! $hasDraw) {
            // Distinguish "no questions at all" from "questions are still
            // drafts" — the second is the common, confusing case: the paper
            // looks full but a draft question can't go live.
            $draftCount = $picked->filter(fn ($qq): bool => $qq->question?->status === 'draft')->count();

            throw ValidationException::withMessages(['questions' => [
                $draftCount > 0
                    ? ($draftCount === 1
                        ? 'One question on this paper is still a draft — publish it in its question bank, then publish the exam.'
                        : "{$draftCount} questions on this paper are still drafts — publish them in their question bank, then publish the exam.")
                    : 'Add questions (or a random-draw rule) before publishing.',
            ]]);
        }

        if ($hasDraw) {
            $this->assertDrawSatisfiable($quiz);
        }

        $firstPublish = $quiz->published_at === null;

        $quiz->forceFill([
            'status' => QuizStatus::Published->value,
            'published_at' => $quiz->published_at ?? now(),
            'total_points' => $fixedPoints,
        ])->save();

        // Class exams announce to every targeted section's students (in-app,
        // queued). Platform mocks are open-lane content — no fan-out.
        if ($firstPublish && ! $quiz->is_platform) {
            $sectionIds = $quiz->targetAssignments()->pluck('section_id')
                ->when($quiz->subject_assignment_id !== null, fn ($ids) => $ids->push($quiz->subjectAssignment?->section_id))
                ->filter()->unique()->values();

            if ($sectionIds->isNotEmpty()) {
                $users = User::query()
                    ->whereHas('studentProfile.enrollments', fn ($q) => $q
                        ->whereIn('section_id', $sectionIds)
                        ->where('status', EnrollmentStatus::Active->value))
                    ->get();

                app(Notifier::class)->toUsers($users, 'lms.quiz_published', [
                    'title' => $quiz->title,
                    'subject' => $quiz->subjectAssignment?->subject?->name ?? '',
                    'kind' => $quiz->kind,
                ], [
                    'link' => '/me/exam',
                    'schoolId' => $quiz->school_id,
                    'branchId' => $quiz->branch_id,
                ]);
            }
        }

        return $quiz;
    }

    /** Close early: no new attempts; live attempts run out their clocks. */
    public function close(Quiz $quiz): Quiz
    {
        $quiz->forceFill(['status' => QuizStatus::Closed->value, 'closed_at' => now()])->save();

        return $quiz;
    }

    /**
     * Every random-draw rule must have enough published questions to draw
     * from — checked at publish so a student can never hit a short paper.
     *
     * @throws ValidationException
     */
    public function assertDrawSatisfiable(Quiz $quiz): void
    {
        foreach ($quiz->draw as $rule) {
            $available = Question::query()
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
                ->count();

            if ($available < (int) $rule['count']) {
                throw ValidationException::withMessages([
                    'draw' => ["A draw rule asks for {$rule['count']} questions but only {$available} match."],
                ]);
            }
        }
    }
}
