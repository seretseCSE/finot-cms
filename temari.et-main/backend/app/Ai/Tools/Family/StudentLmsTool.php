<?php

namespace App\Ai\Tools\Family;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Class-work status for the student/child: open/overdue assignments with
 * due dates and whether a submission is in, plus recently graded work.
 * Parent access rides can_view_grades (same flag the /me child LMS uses).
 */
class StudentLmsTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get homework/assignment status: open and overdue assignments with due dates and submission state, and recently graded work with scores and feedback. Use for questions about homework, deadlines, or missing work.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$student, $link, $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        if (! $this->linkAllows($link, 'can_view_grades')) {
            return $this->deny('Your guardian link does not permit viewing this student\'s class work.');
        }

        $enrollment = $student->currentEnrollment;

        if ($enrollment === null || $enrollment->section_id === null) {
            return $this->deny('The student has no active class enrollment.');
        }

        $assignments = Assignment::query()
            ->where('status', 'published')
            ->whereHas('subjectAssignment', fn ($q) => $q->where('section_id', $enrollment->section_id))
            ->where(function ($q) use ($student): void {
                $q->whereNull('target_student_ids')
                    ->orWhereJsonContains('target_student_ids', $student->id);
            })
            ->with('subjectAssignment.subject:id,name')
            ->orderByDesc('due_at')
            ->limit(30)
            ->get();

        $submissions = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $now = Carbon::now();

        $rows = $assignments->map(function (Assignment $assignment) use ($submissions, $now): array {
            $submission = $submissions->get($assignment->id);
            $due = $assignment->due_at;

            return [
                'title' => $assignment->title,
                'subject' => $assignment->subjectAssignment?->subject?->name,
                'due_at' => $due?->toDateTimeString(),
                'overdue_without_submission' => $due !== null && $due->lessThan($now) && $submission?->submitted_at === null,
                'submitted' => $submission?->submitted_at !== null,
                'late' => (bool) ($submission?->is_late ?? false),
                'score' => $submission?->score !== null ? (float) $submission->score : null,
                'max_score' => $assignment->max_score !== null ? (float) $assignment->max_score : null,
                'feedback' => $submission?->feedback,
            ];
        });

        return $this->ok([
            'student' => $student->full_name,
            'assignments' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('Parent lane only: the child to look at (from my_children). Omit in the student lane.'),
        ];
    }
}
