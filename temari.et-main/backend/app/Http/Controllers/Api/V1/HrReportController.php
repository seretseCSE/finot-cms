<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Support\LeavePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR analytics for the active branch: workforce composition, staff attendance
 * over a window, leave usage for the Ethiopian leave year, and payroll cost.
 * Read-only aggregates — every number traces back to the operational tables.
 */
class HrReportController extends Controller
{
    /** Headline numbers + composition for the HR reports dashboard. */
    public function overview(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        abort_unless(
            $request->user()->hasPermissionForScope('hr.reports.view', $branch->school_id, $branch->id),
            403,
        );

        [$from, $to] = $this->window($request);

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->with('positions')
            ->get();
        $active = $employees->where('is_active', true);

        $activePositions = $active
            ->flatMap(fn (Employee $e) => $e->positions->whereNull('ended_on'));

        // Attendance mix over the window.
        // toBase(): aggregate rows must not run through the model's enum casts.
        $attendance = EmployeeAttendanceRecord::where('branch_id', $branch->id)
            ->whereBetween('date', [$from, $to])
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $attendanceTotal = (int) $attendance->sum();
        $presentish = (int) $attendance->get(EmployeeAttendanceStatus::Present->value, 0)
            + (int) $attendance->get(EmployeeAttendanceStatus::Late->value, 0)
            + (int) $attendance->get(EmployeeAttendanceStatus::HalfDay->value, 0);

        // Leave usage inside the window + open requests right now.
        $leaveByType = LeaveRequest::query()
            ->where('branch_id', $branch->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->selectRaw('leave_types.name, leave_types.is_paid, count(*) as requests, sum(leave_requests.days) as days')
            ->groupBy('leave_types.name', 'leave_types.is_paid')
            ->orderByDesc('days')
            ->get();

        $pendingLeave = LeaveRequest::where('branch_id', $branch->id)
            ->where('status', LeaveRequestStatus::Pending->value)
            ->count();

        // Latest closed payroll cost (approved or paid — frozen numbers only).
        $lastRun = PayrollRun::where('branch_id', $branch->id)
            ->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Paid->value])
            ->orderByDesc('period_end')
            ->first();

        return response()->json([
            'data' => [
                'headcount' => [
                    'total' => $employees->count(),
                    'active' => $active->count(),
                    'inactive' => $employees->count() - $active->count(),
                    'female' => $active->where('gender', 'female')->count(),
                    'male' => $active->where('gender', 'male')->count(),
                ],
                'by_job_title' => $activePositions
                    ->groupBy('job_title')
                    ->map(fn ($rows) => $rows->count())
                    ->sortDesc()
                    ->all(),
                'by_employment_type' => $activePositions
                    ->groupBy(fn ($p) => $p->employment_type?->value ?? 'unspecified')
                    ->map(fn ($rows) => $rows->count())
                    ->sortDesc()
                    ->all(),
                'attendance' => [
                    'recorded' => $attendanceTotal,
                    'by_status' => $attendance,
                    'attendance_rate' => $attendanceTotal > 0 ? round($presentish / $attendanceTotal * 100, 1) : null,
                ],
                'leave' => [
                    'pending_requests' => $pendingLeave,
                    'approved_days' => (float) $leaveByType->sum('days'),
                    'by_type' => $leaveByType,
                ],
                'payroll' => $lastRun === null ? null : [
                    'run_id' => $lastRun->id,
                    'name' => $lastRun->name,
                    'status' => $lastRun->status->value,
                    'period_end' => $lastRun->period_end->toDateString(),
                    'gross_total' => $lastRun->gross_total,
                    'net_total' => $lastRun->net_total,
                    'employer_cost' => round((float) $lastRun->gross_total + (float) $lastRun->pension_employer_total, 2),
                ],
            ],
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Per-employee attendance + leave detail over a window — the printable
     * register summary.
     */
    public function attendance(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        abort_unless(
            $request->user()->hasPermissionForScope('hr.reports.view', $branch->school_id, $branch->id),
            403,
        );

        [$from, $to] = $this->window($request);

        // toBase(): aggregate rows must not run through the model's enum casts.
        $counts = EmployeeAttendanceRecord::where('branch_id', $branch->id)
            ->whereBetween('date', [$from, $to])
            ->toBase()
            ->selectRaw('employee_id, status, count(*) as total')
            ->groupBy('employee_id', 'status')
            ->get()
            ->groupBy('employee_id');

        $leaveDays = LeaveRequest::where('branch_id', $branch->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->selectRaw('employee_id, sum(days) as days')
            ->groupBy('employee_id')
            ->pluck('days', 'employee_id');

        $workingDays = LeavePolicy::workingDays(
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
            $branch->school_id,
            $branch->id,
        );

        $rows = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with('positions')
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) use ($counts, $leaveDays): array {
                $byStatus = ($counts->get($employee->id) ?? collect())->pluck('total', 'status');
                $present = (int) $byStatus->get(EmployeeAttendanceStatus::Present->value, 0);
                $late = (int) $byStatus->get(EmployeeAttendanceStatus::Late->value, 0);
                $halfDay = (int) $byStatus->get(EmployeeAttendanceStatus::HalfDay->value, 0);
                $recorded = (int) $byStatus->sum();

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'job_titles' => $employee->positions->whereNull('ended_on')->pluck('job_title')->values(),
                    'present' => $present,
                    'late' => $late,
                    'half_day' => $halfDay,
                    'absent' => (int) $byStatus->get(EmployeeAttendanceStatus::Absent->value, 0),
                    'excused' => (int) $byStatus->get(EmployeeAttendanceStatus::Excused->value, 0),
                    'recorded' => $recorded,
                    'leave_days' => (float) ($leaveDays[$employee->id] ?? 0),
                    'attendance_rate' => $recorded > 0
                        ? round(($present + $late + $halfDay) / $recorded * 100, 1)
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => ['from' => $from, 'to' => $to, 'working_days' => $workingDays],
        ]);
    }

    /**
     * Chart series for the HR dashboard: the daily attendance register over the
     * window, approved leave per month (last 6), the closed payroll run history
     * (last 6), and the tenure distribution of active staff.
     */
    public function trends(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        abort_unless(
            $request->user()->hasPermissionForScope('hr.reports.view', $branch->school_id, $branch->id),
            403,
        );

        [$from, $to] = $this->window($request);

        // One row per register day (only days that were actually marked), with
        // a column per status. toBase(): aggregates must skip the enum casts.
        $daily = EmployeeAttendanceRecord::where('branch_id', $branch->id)
            ->whereBetween('date', [$from, $to])
            ->toBase()
            ->selectRaw('date, status, count(*) as total')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy(fn (object $row) => CarbonImmutable::parse($row->date)->toDateString())
            ->map(function ($rows, string $date): array {
                $byStatus = $rows->pluck('total', 'status');

                return [
                    'date' => $date,
                    'present' => (int) $byStatus->get(EmployeeAttendanceStatus::Present->value, 0),
                    'late' => (int) $byStatus->get(EmployeeAttendanceStatus::Late->value, 0),
                    'half_day' => (int) $byStatus->get(EmployeeAttendanceStatus::HalfDay->value, 0),
                    'absent' => (int) $byStatus->get(EmployeeAttendanceStatus::Absent->value, 0),
                    'excused' => (int) $byStatus->get(EmployeeAttendanceStatus::Excused->value, 0),
                ];
            })
            ->values();

        // Approved leave days per calendar month (bucketed by start date), split
        // paid/unpaid — the last 6 months including the current one.
        $monthsStart = now()->startOfMonth()->subMonths(5);
        $leaveRows = LeaveRequest::query()
            ->where('leave_requests.branch_id', $branch->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where('start_date', '>=', $monthsStart->toDateString())
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->toBase()
            ->selectRaw("to_char(start_date, 'YYYY-MM') as month, leave_types.is_paid, sum(leave_requests.days) as days")
            ->groupBy('month', 'leave_types.is_paid')
            ->get()
            ->groupBy('month');
        $leaveMonthly = collect(range(0, 5))
            ->map(function (int $offset) use ($monthsStart, $leaveRows): array {
                $month = $monthsStart->copy()->addMonths($offset)->format('Y-m');
                $rows = $leaveRows->get($month) ?? collect();

                return [
                    'month' => $month,
                    'paid' => (float) $rows->where('is_paid', true)->sum('days'),
                    'unpaid' => (float) $rows->where('is_paid', false)->sum('days'),
                ];
            });

        // Closed payroll history — frozen numbers only, oldest first so the
        // chart reads left to right. Deductions = tax + employee pension + other.
        $payrollRuns = PayrollRun::where('branch_id', $branch->id)
            ->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Paid->value])
            ->orderByDesc('period_end')
            ->limit(6)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (PayrollRun $run): array => [
                'run_id' => $run->id,
                'name' => $run->name,
                'period_end' => $run->period_end->toDateString(),
                'net' => (float) $run->net_total,
                'deductions' => round(
                    (float) $run->tax_total + (float) $run->pension_employee_total + (float) $run->deduction_total,
                    2,
                ),
                'employer_pension' => (float) $run->pension_employer_total,
                'employer_cost' => round((float) $run->gross_total + (float) $run->pension_employer_total, 2),
            ]);

        // Years of service for active staff, from the earliest position start.
        $tenure = ['lt1' => 0, '1to3' => 0, '3to5' => 0, '5to10' => 0, 'gte10' => 0, 'unknown' => 0];
        Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with('positions')
            ->get()
            ->each(function (Employee $employee) use (&$tenure): void {
                $hiredOn = $employee->positions->pluck('hired_on')->filter()->min();
                if ($hiredOn === null) {
                    $tenure['unknown']++;

                    return;
                }
                $years = CarbonImmutable::parse($hiredOn)->diffInYears(now());
                $tenure[match (true) {
                    $years < 1 => 'lt1',
                    $years < 3 => '1to3',
                    $years < 5 => '3to5',
                    $years < 10 => '5to10',
                    default => 'gte10',
                }]++;
            });

        return response()->json([
            'data' => [
                'daily' => $daily,
                'leave_monthly' => $leaveMonthly,
                'payroll_runs' => $payrollRuns,
                'tenure' => $tenure,
            ],
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * @return array{string, string}
     */
    private function window(Request $request): array
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();

        abort_if($from > $to, 422, 'The start of the window must be before its end.');

        return [$from, $to];
    }
}
