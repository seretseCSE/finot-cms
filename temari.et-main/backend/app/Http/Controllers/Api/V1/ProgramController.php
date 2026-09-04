<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SchoolProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Education programs of the ACTIVE branch, plus the full catalog — the semester
 * form uses this to offer branch programs first and flag catalog picks that
 * would be added to the branch on save.
 */
class ProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('academic_years.view'), 403);

        $branch = $this->activeBranchOrNull($request);

        // School-wide callers name the branch via ?branch_id — honoured only
        // within the caller's school scope (platform staff: any branch).
        $branchId = $branch?->id;
        if ($branchId === null && ($filterId = $this->branchFilterId($request, null)) !== null) {
            $schoolId = $this->activeSchoolScopeId($request);
            $branchId = Branch::query()
                ->whereKey($filterId)
                ->when($schoolId !== null, fn ($q) => $q->where('school_id', $schoolId))
                ->value('id');
        }

        $programs = $branchId === null ? collect() : SchoolProgram::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('id')
            ->with('gradeLevels:grade_levels.id')
            ->get(['id', 'type', 'name'])
            ->map(fn (SchoolProgram $program) => [
                'id' => $program->id,
                'type' => $program->type,
                'name' => $program->name,
                'grade_level_ids' => $program->gradeLevels->pluck('id')->values(),
            ]);

        return response()->json([
            'data' => [
                'branch_programs' => $programs,
                'catalog' => collect(SchoolProgram::CATALOG)
                    ->map(fn (string $name, string $type) => ['type' => $type, 'name' => $name])
                    ->values(),
            ],
        ]);
    }
}
