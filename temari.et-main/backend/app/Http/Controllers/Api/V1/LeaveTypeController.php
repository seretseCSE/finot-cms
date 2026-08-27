<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Models\School;
use App\Support\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The school's leave policy catalog. Types belong to the SCHOOL (one policy,
 * every branch) and are auto-provisioned from the Ethiopian Labour
 * Proclamation defaults the first time the school opens leave management.
 * Reading requires any leave permission; changing policy is
 * `hr.settings.manage`.
 */
class LeaveTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context to view leave types.');

        $user = $request->user();
        abort_unless(
            $user->hasPermissionForScope('leave.view', $schoolId, $branch?->id)
            || $user->hasPermissionForScope('leave.request_own', $schoolId, $branch?->id),
            403,
        );

        LeavePolicy::provisionDefaults(School::findOrFail($schoolId));

        $types = LeaveType::query()
            ->where('school_id', $schoolId)
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->withCount('requests')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => LeaveTypeResource::collection($types)]);
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context to manage leave types.');
        abort_unless($request->user()->hasPermissionForScope('hr.settings.manage', $schoolId, $branch?->id), 403);

        $data = $this->validated($request, $schoolId);

        $type = LeaveType::create([...$data, 'school_id' => $schoolId, 'is_active' => $data['is_active'] ?? true]);

        return (new LeaveTypeResource($type))
            ->additional(['message' => 'Leave type added.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, LeaveType $leaveType): LeaveTypeResource
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless(
            $leaveType->school_id === $schoolId
            && $request->user()->hasPermissionForScope('hr.settings.manage', $leaveType->school_id, $branch?->id),
            403,
        );

        $leaveType->update($this->validated($request, $leaveType->school_id, $leaveType));

        return new LeaveTypeResource($leaveType);
    }

    public function destroy(Request $request, LeaveType $leaveType): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless(
            $leaveType->school_id === $schoolId
            && $request->user()->hasPermissionForScope('hr.settings.manage', $leaveType->school_id, $branch?->id),
            403,
        );

        // History stays intact (requests keep the FK); a used type is
        // deactivated instead of deleted so past requests keep their meaning.
        if ($leaveType->requests()->exists()) {
            $leaveType->update(['is_active' => false]);

            return response()->json(['message' => 'Leave type has requests, so it was deactivated instead of deleted.']);
        }

        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $schoolId, ?LeaveType $current = null): array
    {
        return $request->validate([
            'name' => [
                $current === null ? 'required' : 'sometimes', 'string', 'max:100',
                Rule::unique('leave_types', 'name')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->ignore($current?->id),
            ],
            'days_per_year' => ['nullable', 'numeric', 'min:0.5', 'max:366'],
            'service_bonus_days' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'service_bonus_every_years' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'is_paid' => ['sometimes', 'boolean'],
            'applicable_gender' => ['nullable', Rule::in(['male', 'female'])],
            'requires_note' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);
    }
}
