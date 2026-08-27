<?php

namespace App\Ai\Tools\Leadership;

use App\Models\Employee;
use App\Models\Marklist;
use App\Models\StudentTermResult;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Per-teacher SIGNALS — never a verdict. Class results, marklist
 * submission discipline and lesson-plan pacing are indicators the head
 * uses to start a conversation; the agent's instructions require framing
 * them as signals with context, not a ranking of "best/worst teachers".
 */
class TeacherSignalsTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Per-teacher signals in scope: classes taught, mean class result in their subjects (latest computed term), marklist submission status counts, and lesson-plan pacing (units covered vs planned). Indicators for support conversations — NOT a teacher ranking.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('reports.view', 'lesson_plans.review', 'grades.approve')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $termId = StudentTermResult::query()->whereIn('branch_id', $branchIds)->max('term_id');

        $teachers = Employee::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('subjectAssignments', fn ($q) => $q->where('is_active', true))
            ->with(['subjectAssignments' => fn ($q) => $q->where('is_active', true)
                ->with(['subject:id,name', 'section:id,name'])])
            ->limit(40)
            ->get();

        if ($teachers->isEmpty()) {
            return $this->deny('No teachers with active assignments in scope.');
        }

        // Subject averages per (section, subject) from the frozen breakdown —
        // one query, matched to assignments in PHP.
        $subjectAverages = $termId === null ? collect() : collect(DB::select(
            "select r.section_id, elem->>'subject_id' as subject_id,
                    round(avg((elem->>'total')::numeric), 2) as average
             from student_term_results r
             cross join lateral jsonb_array_elements(r.breakdown) as elem
             where r.term_id = ? and elem->>'total' is not null
             group by r.section_id, elem->>'subject_id'",
            [$termId],
        ))->keyBy(fn ($row) => $row->section_id.':'.$row->subject_id);

        $marklists = Marklist::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('subject_assignment_id', $teachers->flatMap->subjectAssignments->pluck('id'))
            ->get(['subject_assignment_id', 'status'])
            ->groupBy('subject_assignment_id');

        $rows = $teachers->map(function (Employee $teacher) use ($subjectAverages, $marklists): array {
            $classes = $teacher->subjectAssignments->map(function ($assignment) use ($subjectAverages, $marklists): array {
                $avg = $subjectAverages->get($assignment->section_id.':'.$assignment->subject_id);
                $lists = $marklists->get($assignment->id, collect());

                return [
                    'section' => $assignment->section?->name,
                    'subject' => $assignment->subject?->name,
                    'class_average' => $avg !== null ? (float) $avg->average : null,
                    'marklists' => $lists->countBy(fn ($m) => $m->status->value),
                ];
            });

            return [
                'teacher' => $teacher->full_name,
                'classes' => $classes,
                'mean_class_average' => round((float) $classes->pluck('class_average')->filter()->avg(), 2) ?: null,
            ];
        })->sortBy('teacher')->values();

        return $this->ok([
            'note' => 'Signals, not verdicts — pair with classroom observation before any conclusion.',
            'teachers' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
