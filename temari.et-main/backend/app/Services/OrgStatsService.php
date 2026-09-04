<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\SubjectAssignment;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregated vitals for a school or branch profile — who studies here, who
 * works here (grouped by job title), what is taught, and how full it is.
 * Everything is computed from indexed grouped queries and cached briefly:
 * these are admin dashboards, not ledgers, so 5-minute freshness is fine.
 */
class OrgStatsService
{
    /** Seconds a computed stats payload stays cached. */
    private const TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public function forSchool(School $school): array
    {
        return Cache::remember(
            "org-stats:school:{$school->id}",
            self::TTL,
            fn (): array => [
                ...$this->coreStats('school_id', $school->id),
                'branches' => $this->branchRollup($school),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forBranch(Branch $branch): array
    {
        return Cache::remember(
            "org-stats:branch:{$branch->id}",
            self::TTL,
            fn (): array => $this->coreStats('branch_id', $branch->id),
        );
    }

    /**
     * The shared school/branch aggregate block. `$column` is the tenant anchor
     * (`school_id` or `branch_id`) — every query here is scoped by it, which is
     * what keeps the payload inside the tenant boundary.
     *
     * @return array<string, mixed>
     */
    private function coreStats(string $column, int $id): array
    {
        // Live enrollment seats: active = on a roster, pending = fee-gated.
        $statuses = StudentEnrollment::query()
            ->where($column, $id)
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Gender split of the students actually attending.
        $genders = StudentEnrollment::query()
            ->where("student_enrollments.{$column}", $id)
            ->where('status', EnrollmentStatus::Active->value)
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->whereNull('students.deleted_at')
            ->toBase()
            ->selectRaw('students.gender, count(*) as total')
            ->groupBy('students.gender')
            ->pluck('total', 'gender');

        // Guardians reachable for the students enrolled here (distinct parents).
        $guardians = StudentGuardian::query()
            ->where('is_active', true)
            ->whereHas('student', fn (Builder $q) => $q
                ->whereHas('enrollments', fn (Builder $e) => $e->where($column, $id)->live()))
            ->toBase()
            ->distinct()
            ->count('parent_id');

        // Workforce grouped by job title — multi-job employees count once per
        // title they hold, while the headline total counts people.
        $byJobTitle = EmployeePosition::query()
            ->whereNull('ended_on')
            ->whereHas('employee', fn (Builder $q) => $q->where($column, $id)->where('is_active', true))
            ->toBase()
            ->selectRaw('job_title, count(distinct employee_id) as total')
            ->groupBy('job_title')
            ->orderByDesc('total')
            ->orderBy('job_title')
            ->get()
            ->map(fn (object $row): array => [
                'job_title' => $row->job_title,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $employeesTotal = Employee::query()
            ->where($column, $id)
            ->where('is_active', true)
            ->count();

        // What is taught right now: distinct subjects and teachers with classes
        // in the ACTIVE academic year(s) — closed years don't linger here.
        $activeYearIds = AcademicYear::query()
            ->where($column, $id)
            ->where('status', 'active')
            ->pluck('id');

        $teaching = SubjectAssignment::query()
            ->where($column, $id)
            ->where('is_active', true)
            ->whereIn('academic_year_id', $activeYearIds)
            ->toBase()
            ->selectRaw('count(distinct subject_id) as subjects, count(distinct employee_id) as teachers')
            ->first();

        $sections = Section::query()
            ->where($column, $id)
            ->where('is_active', true)
            ->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(capacity), 0) as capacity')
            ->first();

        return [
            'students' => [
                'active' => (int) ($statuses['active'] ?? 0),
                'pending' => (int) ($statuses['pending'] ?? 0),
                'male' => (int) ($genders['male'] ?? 0),
                'female' => (int) ($genders['female'] ?? 0),
            ],
            'guardians' => $guardians,
            'employees' => [
                'total' => $employeesTotal,
                'by_job_title' => $byJobTitle,
            ],
            'academics' => [
                'subjects_taught' => (int) ($teaching->subjects ?? 0),
                'teachers_teaching' => (int) ($teaching->teachers ?? 0),
                'sections' => (int) ($sections->total ?? 0),
                'capacity' => (int) ($sections->capacity ?? 0),
            ],
            'grades' => $this->perGrade($column, $id),
        ];
    }

    /**
     * Grade-by-grade picture: attending students and active sections per grade
     * level, ordered by the national grade sequence. Grades with sections but no
     * students (and vice versa) still appear. `code` is the compact axis tick
     * (KG1, G4…); `name` is the readable label.
     *
     * @return list<array{id: int, code: string, name: string, students: int, sections: int}>
     */
    private function perGrade(string $column, int $id): array
    {
        $students = StudentEnrollment::query()
            ->where("student_enrollments.{$column}", $id)
            ->where('status', EnrollmentStatus::Active->value)
            ->join('grade_levels', 'grade_levels.id', '=', 'student_enrollments.grade_level_id')
            ->toBase()
            ->selectRaw('grade_levels.id, grade_levels.code, grade_levels.name, grade_levels.sort_order, count(*) as total')
            ->groupBy('grade_levels.id', 'grade_levels.code', 'grade_levels.name', 'grade_levels.sort_order')
            ->get();

        $sections = Section::query()
            ->where("sections.{$column}", $id)
            ->where('sections.is_active', true)
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->toBase()
            ->selectRaw('grade_levels.id, grade_levels.code, grade_levels.name, grade_levels.sort_order, count(*) as total')
            ->groupBy('grade_levels.id', 'grade_levels.code', 'grade_levels.name', 'grade_levels.sort_order')
            ->get();

        $rows = [];
        foreach ($students as $row) {
            $rows[$row->id] = [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'sort_order' => (int) $row->sort_order,
                'students' => (int) $row->total,
                'sections' => 0,
            ];
        }
        foreach ($sections as $row) {
            $rows[$row->id] ??= [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'sort_order' => (int) $row->sort_order,
                'students' => 0,
                'sections' => 0,
            ];
            $rows[$row->id]['sections'] = (int) $row->total;
        }

        usort($rows, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return array_map(function (array $row): array {
            unset($row['sort_order']);

            return $row;
        }, array_values($rows));
    }

    /**
     * Per-branch mini-summary for the school profile — reuses the same list
     * stats the branch tables show, so the numbers always agree.
     *
     * @return list<array<string, mixed>>
     */
    private function branchRollup(School $school): array
    {
        return $school->branches()
            ->withListStats()
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'city' => $branch->city,
                'is_active' => $branch->is_active,
                'students' => (int) $branch->students_count,
                'teachers' => (int) $branch->teachers_count,
                'sections' => (int) $branch->sections_count,
                'grade_min' => $branch->grade_min,
                'grade_max' => $branch->grade_max,
            ])
            ->values()
            ->all();
    }
}
