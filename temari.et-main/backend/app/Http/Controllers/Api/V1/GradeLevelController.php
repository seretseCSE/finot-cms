<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeLevelResource;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Support\GradeOffering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GradeLevelController extends Controller
{
    /**
     * Grade levels for the caller's workspace. In a branch context only the
     * branch's offered grades return (each carrying the program ids offering
     * it, for cascading program pickers); school-wide managers get the union
     * across the school's branches — or one branch via ?branch_id, mirroring
     * how the school-wide workspace loads options for a chosen target branch.
     * Platform staff, and any caller passing ?all=1 (the branch editor needs
     * the full ladder), get the complete national catalog.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Fetch fresh each time to avoid PHP incomplete-object errors when
        // deserializing cached Eloquent models with enum casts across requests.
        $gradeLevels = GradeLevel::orderBy('sort_order')->get();

        if ($request->boolean('all')) {
            return GradeLevelResource::collection($gradeLevels);
        }

        $map = $this->offeringMap($request);

        // Null (platform / no scope) or an unconfigured scope = no narrowing.
        if ($map === null || $map === []) {
            return GradeLevelResource::collection($gradeLevels);
        }

        $scoped = $gradeLevels
            ->filter(fn (GradeLevel $grade) => array_key_exists($grade->id, $map))
            ->each(fn (GradeLevel $grade) => $grade->setAttribute('program_ids', $map[$grade->id]))
            ->values();

        return GradeLevelResource::collection($scoped);
    }

    /**
     * grade_level_id => program ids for the caller's scope, or null when the
     * caller has no tenant scope to narrow by.
     *
     * @return array<int, list<int>>|null
     */
    private function offeringMap(Request $request): ?array
    {
        try {
            $branch = $this->activeBranchOrNull($request);
        } catch (HttpException) {
            // No branch context and not a school manager (defensive — the
            // national ladder is public reference data, never a leak).
            return null;
        }

        $branchId = $branch?->id;

        // School-wide callers may name a target branch via ?branch_id —
        // honoured only within the caller's school scope (platform: any).
        if ($branchId === null && ($filterId = $this->branchFilterId($request, null)) !== null) {
            $schoolId = $this->activeSchoolScopeId($request);
            $branchId = Branch::query()
                ->whereKey($filterId)
                ->when($schoolId !== null, fn ($q) => $q->where('school_id', $schoolId))
                ->value('id');
        }

        if ($branchId !== null) {
            return GradeOffering::map($branchId);
        }

        $schoolId = $this->activeSchoolScopeId($request);

        return $schoolId !== null ? GradeOffering::mapForSchool($schoolId) : null;
    }
}
