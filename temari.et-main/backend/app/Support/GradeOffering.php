<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\StudentEnrollment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The branch's grade × program offering matrix: which national grade levels a
 * branch supports and which of its education programs each grade is offered in
 * (grade_level_school_program pivot). The read side feeds every branch-scoped
 * grade filter; the write side is the ONLY sync path for the matrix, with
 * in-use guards — a grade holding live enrollments or active sections is never
 * silently dropped from the offering.
 */
class GradeOffering
{
    private const CACHE_TTL = 300;

    /**
     * One branch's matrix: grade_level_id => active program ids offering it.
     * Empty array = unconfigured branch (predates the matrix) — treat as "all
     * grades supported" at the call sites.
     *
     * @return array<int, list<int>>
     */
    public static function map(int $branchId): array
    {
        return Cache::remember("branch:{$branchId}:grade-offering", self::CACHE_TTL, function () use ($branchId): array {
            return self::buildMap(fn ($query) => $query->where('school_programs.branch_id', $branchId));
        });
    }

    /**
     * A school's union matrix across all its branches — the school-wide
     * (All branches) workspace filter set.
     *
     * @return array<int, list<int>>
     */
    public static function mapForSchool(int $schoolId): array
    {
        return Cache::remember("school:{$schoolId}:grade-offering", self::CACHE_TTL, function () use ($schoolId): array {
            return self::buildMap(fn ($query) => $query->where('school_programs.school_id', $schoolId));
        });
    }

    /**
     * Grade levels the branch supports (union across its active programs).
     *
     * @return list<int>
     */
    public static function supportedGradeIds(int $branchId): array
    {
        $ids = array_keys(self::map($branchId));

        return $ids !== [] ? $ids : GradeLevel::pluck('id')->all();
    }

    public static function isOffered(int $branchId, int $gradeLevelId, ?int $schoolProgramId = null): bool
    {
        $map = self::map($branchId);

        if ($map === []) {
            return true;
        }

        $programIds = $map[$gradeLevelId] ?? null;

        if ($programIds === null) {
            return false;
        }

        return $schoolProgramId === null || in_array($schoolProgramId, $programIds, true);
    }

    /**
     * Guard for grade-anchored writes (sections, enrollments): the target
     * branch must offer the grade — and the program, when one is named.
     */
    public static function assertOffered(Branch $branch, int $gradeLevelId, ?SchoolProgram $program = null, string $field = 'grade_level_id'): void
    {
        if (self::isOffered($branch->id, $gradeLevelId, $program?->id)) {
            return;
        }

        $grade = GradeLevel::find($gradeLevelId)?->name ?? 'This grade';
        $gradeSupported = array_key_exists($gradeLevelId, self::map($branch->id));

        throw ValidationException::withMessages([
            $field => [$program !== null && $gradeSupported
                ? "{$grade} is not offered in the {$program->name} program at {$branch->name}."
                : "{$grade} is not supported at {$branch->name}. Update the branch's grade offering first.",
            ],
        ]);
    }

    /**
     * The single write path for a branch's programs + matrix (create & edit).
     * Program sync stays ADDITIVE (programs absent from the payload keep their
     * grades — enrollments/terms anchor to them); an entry without
     * grade_level_ids gets every grade. Removals are guarded: a (program,
     * grade) cell with live enrollments, or a grade leaving the branch entirely
     * while it still has active sections, rejects with usage counts.
     *
     * @param  list<array{type: string, grade_level_ids?: list<int>|null}>  $programs
     */
    public static function sync(Branch $branch, array $programs): void
    {
        if ($programs === []) {
            return;
        }

        $allGradeIds = GradeLevel::orderBy('sort_order')->pluck('id')->all();

        // Resolve every payload entry up front so the branch-level diff sees
        // the complete final matrix.
        $wantedByProgram = [];
        $programsById = [];
        foreach ($programs as $entry) {
            $program = SchoolProgram::addToBranch($branch, $entry['type'], withAllGrades: false);
            $programsById[$program->id] = $program;
            $wantedByProgram[$program->id] = ($entry['grade_level_ids'] ?? null) !== null
                ? array_values(array_unique(array_map(intval(...), $entry['grade_level_ids'])))
                : $allGradeIds;
        }

        $currentByProgram = self::currentByProgram($branch);

        $finalByProgram = $wantedByProgram + $currentByProgram;
        $finalUnion = array_unique(array_merge(...array_values($finalByProgram) ?: [[]]));

        // Only enforceable when a grade catalog exists (bare test databases
        // may legitimately have none seeded).
        if ($finalUnion === [] && $allGradeIds !== []) {
            throw ValidationException::withMessages([
                'programs' => ['A branch must offer at least one grade level.'],
            ]);
        }

        self::assertRemovable($branch, $programsById, $wantedByProgram, $currentByProgram, $finalUnion);

        foreach ($wantedByProgram as $programId => $wanted) {
            $programsById[$programId]->gradeLevels()->sync($wanted);
        }

        self::bust($branch);
    }

    /**
     * Correlated subselect for list stats: the lowest/highest grade NAME a
     * branch (or school) OFFERS — the configured matrix, not the sections that
     * happen to exist. Rows predating the matrix (no offering rows) fall back
     * to their active sections' grades so the span never lies. Pair with
     * `addSelect(['grade_min' => GradeOffering::gradeEdge('asc'), ...])`.
     *
     * @param  'branch'|'school'  $scope
     * @return \Illuminate\Database\Eloquent\Builder<GradeLevel>
     */
    public static function gradeEdge(string $direction, string $scope = 'branch'): \Illuminate\Database\Eloquent\Builder
    {
        [$ownerColumn, $programColumn, $sectionColumn] = $scope === 'school'
            ? ['schools.id', 'school_programs.school_id', 'sections.school_id']
            : ['branches.id', 'school_programs.branch_id', 'sections.branch_id'];

        $offering = fn (Builder $query): Builder => $query
            ->from('grade_level_school_program')
            ->join('school_programs', 'school_programs.id', '=', 'grade_level_school_program.school_program_id')
            ->whereColumn($programColumn, $ownerColumn)
            ->where('school_programs.is_active', true)
            ->whereNull('school_programs.deleted_at');

        return GradeLevel::query()
            ->where(function ($query) use ($offering, $ownerColumn, $sectionColumn): void {
                $query
                    ->whereExists(fn (Builder $sub) => $offering($sub)
                        ->whereColumn('grade_level_school_program.grade_level_id', 'grade_levels.id'))
                    ->orWhere(fn ($unconfigured) => $unconfigured
                        ->whereNotExists(fn (Builder $sub) => $offering($sub))
                        ->whereExists(fn (Builder $sub) => $sub
                            ->from('sections')
                            ->whereColumn($sectionColumn, $ownerColumn)
                            ->whereColumn('sections.grade_level_id', 'grade_levels.id')
                            ->where('sections.is_active', true)
                            ->whereNull('sections.deleted_at')));
            })
            ->orderBy('sort_order', $direction)
            ->select('name')
            ->limit(1);
    }

    /** Drop the cached matrices after any write that touches them. */
    public static function bust(Branch $branch): void
    {
        Cache::forget("branch:{$branch->id}:grade-offering");
        Cache::forget("school:{$branch->school_id}:grade-offering");
    }

    /**
     * @param  array<int, SchoolProgram>  $programsById
     * @param  array<int, list<int>>  $wantedByProgram
     * @param  array<int, list<int>>  $currentByProgram
     * @param  list<int>  $finalUnion
     */
    private static function assertRemovable(Branch $branch, array $programsById, array $wantedByProgram, array $currentByProgram, array $finalUnion): void
    {
        $errors = [];
        $gradeNames = GradeLevel::pluck('name', 'id');

        foreach ($wantedByProgram as $programId => $wanted) {
            $removing = array_diff($currentByProgram[$programId] ?? [], $wanted);
            if ($removing === []) {
                continue;
            }

            // Live enrollments anchor to the (program, grade) cell.
            $enrolled = StudentEnrollment::query()
                ->where('school_program_id', $programId)
                ->whereIn('grade_level_id', $removing)
                ->live()
                ->selectRaw('grade_level_id, count(*) as total')
                ->groupBy('grade_level_id')
                ->pluck('total', 'grade_level_id');

            foreach ($enrolled as $gradeId => $total) {
                $errors[] = "{$gradeNames[$gradeId]} ({$programsById[$programId]->name}) still has {$total} live ".
                    ($total === 1 ? 'enrollment' : 'enrollments').' — move or withdraw them first.';
            }
        }

        // Sections carry a grade but no program: they only block a grade that
        // is leaving the branch entirely.
        $currentUnion = array_unique(array_merge(...array_values($currentByProgram) ?: [[]]));
        $leavingBranch = array_diff($currentUnion, $finalUnion);

        if ($leavingBranch !== []) {
            $sections = $branch->sections()
                ->where('is_active', true)
                ->whereIn('grade_level_id', $leavingBranch)
                ->selectRaw('grade_level_id, count(*) as total')
                ->groupBy('grade_level_id')
                ->pluck('total', 'grade_level_id');

            foreach ($sections as $gradeId => $total) {
                $errors[] = "{$gradeNames[$gradeId]} still has {$total} active ".
                    ($total === 1 ? 'section' : 'sections').' — archive them first.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['programs' => $errors]);
        }
    }

    /**
     * Current grade ids per active program for one branch (uncached — write
     * path only).
     *
     * @return array<int, list<int>>
     */
    private static function currentByProgram(Branch $branch): array
    {
        $rows = DB::table('grade_level_school_program')
            ->join('school_programs', 'school_programs.id', '=', 'grade_level_school_program.school_program_id')
            ->where('school_programs.branch_id', $branch->id)
            ->where('school_programs.is_active', true)
            ->whereNull('school_programs.deleted_at')
            ->get(['grade_level_school_program.grade_level_id', 'grade_level_school_program.school_program_id']);

        $current = [];
        foreach ($rows as $row) {
            $current[(int) $row->school_program_id][] = (int) $row->grade_level_id;
        }

        return $current;
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return array<int, list<int>>
     */
    private static function buildMap(callable $scope): array
    {
        $rows = DB::table('grade_level_school_program')
            ->join('school_programs', 'school_programs.id', '=', 'grade_level_school_program.school_program_id')
            ->where('school_programs.is_active', true)
            ->whereNull('school_programs.deleted_at')
            ->tap(fn ($query) => $scope($query))
            ->get(['grade_level_school_program.grade_level_id', 'grade_level_school_program.school_program_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->grade_level_id][] = (int) $row->school_program_id;
        }

        return $map;
    }
}
