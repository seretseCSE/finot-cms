<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The school's non-working-day calendar (Ethiopian public holidays + school
 * closures). School-owned; a row may be narrowed to one branch. Feeds the
 * employee-attendance register and the leave working-day calculation.
 */
class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context to view holidays.');

        $user = $request->user();
        abort_unless(
            $user->hasPermissionForScope('leave.view', $schoolId, $branch?->id)
            || $user->hasPermissionForScope('employee_attendance.view', $schoolId, $branch?->id)
            || $user->hasPermissionForScope('leave.request_own', $schoolId, $branch?->id),
            403,
        );

        $holidays = Holiday::query()
            ->where('school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('branch_id')->orWhere('branch_id', $branch->id)))
            ->when($request->filled('from'), fn ($q) => $q->where('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('date', '<=', $request->date('to')))
            ->with('branch:id,name')
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $h) => $this->present($h));

        return response()->json(['data' => $holidays]);
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context to manage holidays.');
        abort_unless($request->user()->hasPermissionForScope('hr.settings.manage', $schoolId, $branch?->id), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('school_id', $schoolId)],
        ]);

        $holiday = Holiday::create([...$data, 'school_id' => $schoolId]);

        return response()->json([
            'data' => $this->present($holiday->load('branch:id,name')),
            'message' => 'Holiday added.',
        ], 201);
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless(
            $holiday->school_id === $schoolId
            && $request->user()->hasPermissionForScope('hr.settings.manage', $holiday->school_id, $branch?->id),
            403,
        );

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'date' => ['sometimes', 'required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('school_id', $holiday->school_id)],
        ]);

        $holiday->update($data);

        return response()->json(['data' => $this->present($holiday->load('branch:id,name'))]);
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless(
            $holiday->school_id === $schoolId
            && $request->user()->hasPermissionForScope('hr.settings.manage', $holiday->school_id, $branch?->id),
            403,
        );

        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Holiday $holiday): array
    {
        return [
            'id' => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'branch_id' => $holiday->branch_id,
            'branch_name' => $holiday->branch?->name,
        ];
    }
}
