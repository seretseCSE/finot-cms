<?php

namespace App\Ai\Tools\Leadership;

use App\Models\Employee;
use App\Models\Section;
use App\Models\StudentEnrollment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The school at a glance: enrollment by grade (and branch), section counts
 * with capacity pressure, staff headcount. The natural first tool call for
 * most leadership questions.
 */
class SchoolOverviewTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'School overview: active enrollment counts by grade and branch, section counts and average section fill, and staff headcount. Call this first for broad "how is my school" questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('reports.view', 'students.view', 'grades.manage')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $enrollment = StudentEnrollment::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['pending', 'active'])
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->join('grade_levels', 'grade_levels.id', '=', 'student_enrollments.grade_level_id')
            ->join('branches', 'branches.id', '=', 'student_enrollments.branch_id')
            ->selectRaw('branches.name as branch, grade_levels.name as grade, grade_levels.sort_order, count(*) as students')
            ->groupBy('branches.name', 'grade_levels.name', 'grade_levels.sort_order')
            ->orderBy('branches.name')
            ->orderBy('grade_levels.sort_order')
            ->get()
            ->map(fn ($row): array => [
                'branch' => $row->branch,
                'grade' => $row->grade,
                'students' => (int) $row->students,
            ]);

        $sections = Section::query()
            ->whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->withCount(['enrollments as active_students' => fn ($q) => $q
                ->whereIn('status', ['pending', 'active'])
                ->whereHas('academicYear', fn ($y) => $y->where('status', 'active'))])
            ->get(['id', 'name', 'capacity', 'branch_id']);

        $staff = Employee::query()
            ->whereIn('branch_id', $branchIds)
            ->count();

        return $this->ok([
            'total_active_students' => $enrollment->sum('students'),
            'enrollment_by_grade' => $enrollment,
            'sections' => [
                'count' => $sections->count(),
                'over_capacity' => $sections->filter(fn ($s) => $s->capacity !== null && $s->active_students > $s->capacity)
                    ->map(fn ($s): array => ['section' => $s->name, 'students' => (int) $s->active_students, 'capacity' => (int) $s->capacity])
                    ->values(),
            ],
            'staff_count' => $staff,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
