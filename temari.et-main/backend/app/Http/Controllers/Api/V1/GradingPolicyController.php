<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradingPolicyResource;
use App\Models\GradingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Grading policies: which scale + display mode (numeric/letter/both) applies
 * to which grade-level window. School-wide rows (branch_id null) are the
 * default; branch rows override — a branch can grade KG descriptively and
 * high school with letters while a sister branch stays numeric. Managed by
 * the same supervisory lane that owns grade books (grades.manage); the
 * school-wide workspace targets a branch explicitly via `branch_id`.
 */
class GradingPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$schoolId, $branchId] = $this->schoolScope($request);

        abort_unless($request->user()->hasPermissionForScope('grades.view', $schoolId, $branchId), 403);

        $policies = GradingPolicy::query()
            ->where('school_id', $schoolId)
            ->with(['scale.bands', 'branch:id,name'])
            ->orderByRaw('branch_id IS NOT NULL, branch_id')
            ->orderByRaw('min_grade_sort NULLS FIRST')
            ->get();

        return response()->json(['data' => GradingPolicyResource::collection($policies)]);
    }

    public function store(Request $request): JsonResponse
    {
        [$schoolId, $branchId] = $this->schoolScope($request);

        abort_unless($request->user()->hasPermissionForScope('grades.manage', $schoolId, $branchId), 403);

        $data = $this->validated($request, $schoolId);

        // A branch-scoped director always writes rows for their own branch;
        // school managers choose (null = school-wide default row).
        $targetBranchId = $branchId ?? $data['branch_id'] ?? null;

        if ($targetBranchId !== null) {
            abort_unless(
                $request->user()->hasPermissionForScope('grades.manage', $schoolId, (int) $targetBranchId),
                403,
            );
        }

        $this->assertWindowFree($schoolId, $targetBranchId, $data, null);

        $policy = GradingPolicy::create([
            'school_id' => $schoolId,
            'branch_id' => $targetBranchId,
            'grading_scale_id' => $data['grading_scale_id'],
            'display' => $data['display'],
            'min_grade_sort' => $data['min_grade_sort'] ?? null,
            'max_grade_sort' => $data['max_grade_sort'] ?? null,
        ]);

        return (new GradingPolicyResource($policy->load(['scale.bands', 'branch:id,name'])))
            ->additional(['message' => 'Grading policy saved.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, GradingPolicy $gradingPolicy): GradingPolicyResource
    {
        $this->authorizePolicy($request, $gradingPolicy);

        $data = $this->validated($request, $gradingPolicy->school_id);

        $this->assertWindowFree($gradingPolicy->school_id, $gradingPolicy->branch_id, $data, $gradingPolicy->id);

        $gradingPolicy->update([
            'grading_scale_id' => $data['grading_scale_id'],
            'display' => $data['display'],
            'min_grade_sort' => $data['min_grade_sort'] ?? null,
            'max_grade_sort' => $data['max_grade_sort'] ?? null,
        ]);

        return new GradingPolicyResource($gradingPolicy->load(['scale.bands', 'branch:id,name']));
    }

    public function destroy(Request $request, GradingPolicy $gradingPolicy): JsonResponse
    {
        $this->authorizePolicy($request, $gradingPolicy);

        $gradingPolicy->delete();

        return response()->json(['message' => 'Grading policy removed.']);
    }

    private function authorizePolicy(Request $request, GradingPolicy $policy): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.manage', $policy->school_id, $policy->branch_id),
            403,
        );
    }

    /**
     * @return array{0: int, 1: ?int}
     */
    private function schoolScope(Request $request): array
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context to manage grading.');

        return [(int) $schoolId, $branch?->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $schoolId): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('school_id', $schoolId)],
            'grading_scale_id' => [
                'required', 'integer',
                Rule::exists('grading_scales', 'id')->where(
                    fn ($q) => $q->where(fn ($s) => $s->whereNull('school_id')->orWhere('school_id', $schoolId))
                ),
            ],
            'display' => ['required', Rule::in(GradingPolicy::DISPLAYS)],
            'min_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50', 'gte:min_grade_sort'],
        ]);
    }

    /**
     * Grade windows must not overlap within one scope (school-wide, or one
     * branch) — every grade level resolves to at most one row per layer.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertWindowFree(int $schoolId, ?int $branchId, array $data, ?int $ignoreId): void
    {
        $min = $data['min_grade_sort'] ?? null;
        $max = $data['max_grade_sort'] ?? null;

        $clash = GradingPolicy::query()
            ->where('school_id', $schoolId)
            ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            // Open-ended bounds clash with everything on that side.
            ->when($max !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('min_grade_sort')->orWhere('min_grade_sort', '<=', $max)))
            ->when($min !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('max_grade_sort')->orWhere('max_grade_sort', '>=', $min)))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'min_grade_sort' => ['Another policy already covers part of this grade range.'],
            ]);
        }
    }
}
