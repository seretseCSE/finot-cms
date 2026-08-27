<?php

namespace App\Actions;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\LeavePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Leave balances for a set of employees in one Ethiopian leave year:
 * entitlement (per LeavePolicy, service-aware) minus approved days, with
 * pending days shown separately so approvers can see what is already
 * committed before deciding.
 */
class ComputeLeaveBalancesAction
{
    /**
     * @param  Collection<int, Employee>  $employees  with `positions` loaded
     * @param  Collection<int, LeaveType>  $types
     * @return list<array<string, mixed>>
     */
    public function execute(Collection $employees, Collection $types, CarbonImmutable $asOf): array
    {
        $year = LeavePolicy::leaveYear($asOf);

        // One query for the whole set: cumulative service start per employee
        // (school-wide earliest hire — Art. 77 tenure survives branch moves).
        $serviceStarts = LeavePolicy::serviceStarts($employees);

        // One aggregate query: employee × type → approved/pending day sums for
        // requests STARTING inside the leave year.
        $usage = LeaveRequest::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereIn('status', [LeaveRequestStatus::Approved->value, LeaveRequestStatus::Pending->value])
            ->whereBetween('start_date', [$year['start']->toDateString(), $year['end']->toDateString()])
            ->selectRaw('employee_id, leave_type_id, status, sum(days) as total_days')
            ->groupBy('employee_id', 'leave_type_id', 'status')
            ->get()
            ->groupBy(fn ($row) => $row->employee_id.':'.$row->leave_type_id);

        return $employees
            ->map(function (Employee $employee) use ($types, $usage, $asOf, $serviceStarts): array {
                $balances = $types
                    ->filter(fn (LeaveType $type) => $type->applicable_gender === null
                        || $employee->gender === null
                        || $type->applicable_gender === $employee->gender)
                    ->map(function (LeaveType $type) use ($employee, $usage, $asOf, $serviceStarts): array {
                        $rows = $usage->get($employee->id.':'.$type->id, collect());
                        $taken = (float) $rows->firstWhere('status', LeaveRequestStatus::Approved)?->total_days;
                        $pending = (float) $rows->firstWhere('status', LeaveRequestStatus::Pending)?->total_days;
                        $entitled = LeavePolicy::entitlementFrom($type, $serviceStarts[$employee->id] ?? null, $asOf);

                        return [
                            'leave_type_id' => $type->id,
                            'leave_type_name' => $type->name,
                            'leave_type_code' => $type->code,
                            'is_paid' => $type->is_paid,
                            'entitled' => $entitled,
                            'taken' => $taken,
                            'pending' => $pending,
                            'remaining' => $entitled === null ? null : round($entitled - $taken, 1),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'balances' => $balances,
                ];
            })
            ->values()
            ->all();
    }
}
