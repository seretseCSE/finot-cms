<?php

namespace App\Services\Lms;

use App\Enums\QuizAttemptStatus;
use App\Enums\TermStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SubjectAssignment;
use Illuminate\Support\Facades\DB;

/**
 * The bridge from LMS scores into the continuous-assessment gradebook
 * (ADR-016): a quiz/assignment linked to an `assessments` slot pushes its
 * results into `assessment_results`, rescaled to the slot's max — from there
 * the existing marklist → frozen term results → report card pipeline takes
 * over. No double entry, no parallel marks store.
 *
 * Locked marklists and closed terms are never written through — re-syncing
 * happens when the teacher reopens, via the quiz/assignment sync endpoints.
 */
class GradebookSync
{
    /**
     * Push one student's (or every taker's) counting quiz attempt into the
     * linked slot. The counting attempt follows the quiz's `grade_attempt`
     * setting: best (default) | last | first, among graded attempts.
     */
    public function syncQuiz(Quiz $quiz, ?int $studentId = null): int
    {
        if ($quiz->assessment === null || $quiz->subjectAssignment === null) {
            return 0;
        }

        // Multi-section exams write into EACH section's own gradebook: the
        // anchor class uses the linked slot directly; sibling sections use
        // their materialisation of the same template slot (or, for ad-hoc
        // slots, the same-named assessment).
        $count = 0;

        foreach ($quiz->targetAssignments()->with('marklist', 'term')->get() as $assignment) {
            $assessment = $this->assessmentFor($quiz, $assignment);

            if ($assessment === null || ! $this->anchorWritable($assignment)) {
                continue;
            }

            $count += $this->syncQuizSection($quiz, $assignment, $assessment, $studentId);
        }

        return $count;
    }

    /** The quiz's gradebook slot as materialised in one target section. */
    private function assessmentFor(Quiz $quiz, SubjectAssignment $assignment): ?Assessment
    {
        $linked = $quiz->assessment;

        if ($linked === null) {
            return null;
        }

        if ((int) $linked->subject_assignment_id === (int) $assignment->id) {
            return $linked;
        }

        return Assessment::query()
            ->where('subject_assignment_id', $assignment->id)
            ->when(
                $linked->continuous_assessment_item_id !== null,
                fn ($q) => $q->where('continuous_assessment_item_id', $linked->continuous_assessment_item_id),
                fn ($q) => $q->where('name', $linked->name)->where('max_score', $linked->max_score),
            )
            ->first();
    }

    private function syncQuizSection(Quiz $quiz, SubjectAssignment $assignment, Assessment $assessment, ?int $studentId): int
    {
        $attempts = $quiz->attempts()
            ->where('status', QuizAttemptStatus::Graded->value)
            ->whereNotNull('student_id')
            ->whereHas('enrollment', fn ($q) => $q->where('section_id', $assignment->section_id))
            ->when($studentId !== null, fn ($q) => $q->where('student_id', $studentId))
            ->orderBy('submitted_at')
            ->get()
            ->groupBy('student_id');

        if ($attempts->isEmpty()) {
            return 0;
        }

        $policy = (string) $quiz->setting('grade_attempt', 'best');
        $max = (float) $assessment->max_score;

        $rows = $attempts->map(function ($group) use ($policy): QuizAttempt {
            return match ($policy) {
                'first' => $group->first(),
                'last' => $group->last(),
                default => $group->sortByDesc(fn (QuizAttempt $a): float => (float) $a->score)->first(),
            };
        })->map(fn (QuizAttempt $attempt): array => [
            'assessment_id' => $assessment->id,
            'student_id' => $attempt->student_id,
            'score' => $attempt->max_score > 0
                ? min(round((float) $attempt->score / (float) $attempt->max_score * $max, 2), $max)
                : 0,
            'is_absent' => false,
            'remarks' => null,
            'recorded_by' => $assignment->employee_id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->values();

        DB::table('assessment_results')->upsert(
            $rows->all(),
            ['assessment_id', 'student_id'],
            ['score', 'is_absent', 'recorded_by', 'updated_at'],
        );

        return $rows->count();
    }

    /** Push one graded submission into the assignment's linked slot. */
    public function syncSubmission(AssignmentSubmission $submission): bool
    {
        $assignment = $submission->assignment;

        if ($assignment === null || $submission->score === null) {
            return false;
        }

        $assessment = $assignment->assessment;
        $anchor = $assignment->subjectAssignment;

        if ($assessment === null || $anchor === null || ! $this->writableAssignment($assignment)) {
            return false;
        }

        // Rescale from the assignment's own max to the gradebook slot's max.
        $sourceMax = (float) ($assignment->max_score ?? $assessment->max_score);
        $scaled = $sourceMax > 0
            ? round((float) $submission->score / $sourceMax * (float) $assessment->max_score, 2)
            : 0;

        DB::table('assessment_results')->upsert(
            [[
                'assessment_id' => $assignment->assessment_id,
                'student_id' => $submission->student_id,
                'score' => min($scaled, (float) $assessment->max_score),
                'is_absent' => false,
                'remarks' => null,
                'recorded_by' => $anchor->employee_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['assessment_id', 'student_id'],
            ['score', 'is_absent', 'recorded_by', 'updated_at'],
        );

        return true;
    }

    /** Re-push every graded submission of an assignment (after reopen). */
    public function resyncAssignment(Assignment $assignment): int
    {
        $count = 0;

        foreach ($assignment->submissions()->whereNotNull('score')->get() as $submission) {
            $count += $this->syncSubmission($submission) ? 1 : 0;
        }

        return $count;
    }

    private function writableAssignment(Assignment $assignment): bool
    {
        return $this->anchorWritable($assignment->subjectAssignment);
    }

    /** Never write through a locked marklist or into a closed term. */
    private function anchorWritable(?SubjectAssignment $anchor): bool
    {
        if ($anchor === null) {
            return false;
        }

        $marklist = $anchor->marklist;
        if ($marklist !== null && $marklist->isLocked()) {
            return false;
        }

        $anchor->loadMissing('term');

        return $anchor->term === null || $anchor->term->status !== TermStatus::Closed;
    }
}
