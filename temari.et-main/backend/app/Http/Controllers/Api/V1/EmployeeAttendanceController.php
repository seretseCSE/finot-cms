<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmployeeAttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendanceRecord;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\Ethiopia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The daily employee register. It lists every active employee of the
 * branch with their recorded mark for the day, OVERLAYED with approved leave
 * and the holiday calendar (computed, never materialised as records — the
 * sources can't drift). Employees view their own history via `mine`.
 */
class EmployeeAttendanceController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('employee_attendance.view', $branch->school_id, $branch->id),
            403,
        );

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with('positions')
            ->orderBy('first_name')
            ->get();

        $marks = EmployeeAttendanceRecord::where('branch_id', $branch->id)
            ->where('date', $date)
            ->get()
            ->keyBy('employee_id');

        // Approved leave covering the day, keyed by employee.
        $onLeave = LeaveRequest::query()
            ->where('branch_id', $branch->id)
            ->approvedOverlapping($date, $date)
            ->with('leaveType:id,name,code')
            ->get()
            ->keyBy('employee_id');

        $holiday = Holiday::where('school_id', $branch->school_id)
            ->where('date', $date)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->first();

        $roster = $employees->map(function (Employee $employee) use ($marks, $onLeave): array {
            $mark = $marks->get($employee->id);
            $leave = $onLeave->get($employee->id);

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'phone' => $employee->phone,
                'job_titles' => $employee->positions->whereNull('ended_on')->pluck('job_title')->values(),
                'expected_check_in' => $employee->check_in ? substr((string) $employee->check_in, 0, 5) : null,
                'expected_check_out' => $employee->check_out ? substr((string) $employee->check_out, 0, 5) : null,
                'status' => $mark?->status->value,
                'source' => $mark?->source,
                'check_in' => $mark?->check_in ? substr((string) $mark->check_in, 0, 5) : null,
                'check_out' => $mark?->check_out ? substr((string) $mark->check_out, 0, 5) : null,
                'note' => $mark?->note,
                'on_leave' => $leave === null ? null : [
                    'leave_request_id' => $leave->id,
                    'leave_type_name' => $leave->leaveType?->name,
                    'is_half_day' => $leave->is_half_day,
                    'until' => $leave->end_date->toDateString(),
                ],
            ];
        })->values();

        return response()->json([
            'data' => $roster,
            'meta' => [
                'date' => $date,
                'is_weekend' => now()->parse($date)->isWeekend(),
                'holiday' => $holiday?->only(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('employee_attendance.record', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:'.Ethiopia::today()],
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.employee_id' => [
                'required', 'integer',
                Rule::exists('employees', 'id')->where('branch_id', $branch->id)->whereNull('deleted_at'),
            ],
            'records.*.status' => ['required', new Enum(EmployeeAttendanceStatus::class)],
            'records.*.check_in' => ['nullable', 'date_format:H:i'],
            'records.*.check_out' => ['nullable', 'date_format:H:i'],
            'records.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $branch, $user): void {
            $existing = EmployeeAttendanceRecord::query()
                ->whereIn('employee_id', collect($data['records'])->pluck('employee_id'))
                ->whereDate('date', $data['date'])
                ->get()
                ->keyBy('employee_id');

            foreach ($data['records'] as $record) {
                $values = [
                    'status' => $record['status'],
                    'check_in' => $record['check_in'] ?? null,
                    'check_out' => $record['check_out'] ?? null,
                    'note' => $record['note'] ?? null,
                ];

                $current = $existing->get($record['employee_id']);

                if ($current !== null) {
                    // Re-saving an untouched row must not flip a device mark
                    // to "manual" — only an actual human edit claims it.
                    $current->fill($values);

                    if ($current->isDirty()) {
                        $current->fill(['source' => 'manual', 'recorded_by' => $user->id])->save();
                    }

                    continue;
                }

                EmployeeAttendanceRecord::create($values + [
                    'employee_id' => $record['employee_id'],
                    'date' => $data['date'],
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'source' => 'manual',
                    'recorded_by' => $user->id,
                ]);
            }
        });

        return response()->json([
            'message' => 'Employee attendance saved.',
            'meta' => ['saved' => count($data['records']), 'date' => $data['date']],
        ]);
    }

    /** The signed-in employee's own attendance history (self-service). */
    public function mine(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('leave.request_own', $branch->school_id, $branch->id)
            || $user->hasPermissionForScope('employee_attendance.view', $branch->school_id, $branch->id),
            403,
        );

        $employee = Employee::where('user_id', $user->id)->where('branch_id', $branch->id)->first();
        abort_if($employee === null, 422, 'No employee profile found for your account in this branch.');

        $from = $request->date('from')?->toDateString() ?? now()->subDays(30)->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();

        $records = EmployeeAttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->get()
            ->map(fn (EmployeeAttendanceRecord $r) => [
                'date' => $r->date->toDateString(),
                'status' => $r->status->value,
                'source' => $r->source,
                'check_in' => $r->check_in ? substr((string) $r->check_in, 0, 5) : null,
                'check_out' => $r->check_out ? substr((string) $r->check_out, 0, 5) : null,
                'note' => $r->note,
            ]);

        return response()->json([
            'data' => $records,
            'meta' => ['from' => $from, 'to' => $to, 'employee_id' => $employee->id],
        ]);
    }
}
