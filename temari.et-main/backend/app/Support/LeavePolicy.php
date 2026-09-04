<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\School;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Leave policy per the Ethiopian Labour Proclamation 1156/2019, applied as
 * sensible defaults every school can adjust. Also owns the two calculations
 * the whole module leans on:
 *
 *  - working days in a window (weekends + the school's holiday calendar are
 *    not consumed by leave), and
 *  - the entitlement for an employee × type in a leave year (annual leave
 *    grows with service — Art. 77: 16 working days + 1 per 2 extra years).
 *
 * The LEAVE YEAR is the Ethiopian year: Meskerem 1 ≈ Sep 11 (Sep 12 after a
 * Gregorian leap year). Balances are computed against that window, matching
 * how Ethiopian schools plan (academic year + E.C. payroll months).
 */
class LeavePolicy
{
    /**
     * Statutory defaults provisioned for a school the first time it touches
     * leave management.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            // Art. 77: 16 working days, +1 per 2 additional years of service.
            ['code' => 'annual', 'name' => 'Annual leave', 'days_per_year' => 16, 'service_bonus_days' => 1, 'service_bonus_every_years' => 2, 'is_paid' => true, 'applicable_gender' => null, 'requires_note' => false, 'sort_order' => 1],
            // Art. 85–86: up to 6 months per year (pay tapers 100%/50%/0 —
            // payroll handles pay; here we track the days). Needs a medical note.
            ['code' => 'sick', 'name' => 'Sick leave', 'days_per_year' => 180, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => true, 'applicable_gender' => null, 'requires_note' => true, 'sort_order' => 2],
            // Art. 88: 30 days prenatal + 90 days postnatal, fully paid.
            ['code' => 'maternity', 'name' => 'Maternity leave', 'days_per_year' => 120, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => true, 'applicable_gender' => 'female', 'requires_note' => false, 'sort_order' => 3],
            // Art. 81(2): 3 working days paid paternity leave.
            ['code' => 'paternity', 'name' => 'Paternity leave', 'days_per_year' => 3, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => true, 'applicable_gender' => 'male', 'requires_note' => false, 'sort_order' => 4],
            // Art. 81(1)(a): 3 working days paid for one's own wedding.
            ['code' => 'marriage', 'name' => 'Marriage leave', 'days_per_year' => 3, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => true, 'applicable_gender' => null, 'requires_note' => false, 'sort_order' => 5],
            // Art. 81(1)(b): 3 working days paid on death of a close relative.
            ['code' => 'bereavement', 'name' => 'Bereavement leave', 'days_per_year' => 3, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => true, 'applicable_gender' => null, 'requires_note' => false, 'sort_order' => 6],
            // Art. 81(3): up to 5 consecutive days unpaid for exceptional events.
            ['code' => 'unpaid', 'name' => 'Unpaid personal leave', 'days_per_year' => null, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => false, 'applicable_gender' => null, 'requires_note' => false, 'sort_order' => 7],
            // Common school practice: exam / study days for staff upgrading.
            ['code' => 'study', 'name' => 'Study / exam leave', 'days_per_year' => 10, 'service_bonus_days' => 0, 'service_bonus_every_years' => 0, 'is_paid' => true, 'applicable_gender' => null, 'requires_note' => true, 'sort_order' => 8],
        ];
    }

    /** Provision the statutory catalog for a school that has no types yet. */
    public static function provisionDefaults(School $school): void
    {
        if (LeaveType::withTrashed()->where('school_id', $school->id)->exists()) {
            return;
        }

        foreach (self::defaults() as $type) {
            LeaveType::create([...$type, 'school_id' => $school->id, 'is_active' => true]);
        }
    }

    /**
     * The Ethiopian leave year containing $date: Meskerem 1 (Sep 11, or Sep 12
     * after a Gregorian leap year) through the eve of the next one.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public static function leaveYear(CarbonImmutable $date): array
    {
        $start = self::meskerem1($date->year);
        if ($date->lessThan($start)) {
            $start = self::meskerem1($date->year - 1);
        }

        return ['start' => $start, 'end' => self::meskerem1($start->year + 1)->subDay()];
    }

    /** Ethiopian new year day in a given Gregorian year. */
    public static function meskerem1(int $gregorianYear): CarbonImmutable
    {
        // Meskerem 1 falls on Sep 12 when the FOLLOWING Gregorian year is a leap year.
        $day = CarbonImmutable::create($gregorianYear + 1, 1, 1)->isLeapYear() ? 12 : 11;

        return CarbonImmutable::create($gregorianYear, 9, $day);
    }

    /**
     * Working days consumed by a leave window: calendar days minus weekends
     * (Sat + Sun) and the school's holiday calendar for that branch.
     */
    public static function workingDays(CarbonImmutable $start, CarbonImmutable $end, int $schoolId, ?int $branchId): float
    {
        $holidays = Holiday::where('school_id', $schoolId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where(fn ($q) => $q->whereNull('branch_id')->when($branchId !== null, fn ($qq) => $qq->orWhere('branch_id', $branchId)))
            ->pluck('date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())
            ->all();

        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if (! $day->isWeekend() && ! in_array($day->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        return (float) $days;
    }

    /**
     * Entitled days for an employee × type in the leave year, or null when the
     * type has no cap. Resolves the employee's cumulative service start itself;
     * bulk paths should precompute starts via serviceStarts() and call
     * entitlementFrom() instead.
     */
    public static function entitlement(LeaveType $type, Employee $employee, CarbonImmutable $asOf): ?float
    {
        $needsService = $type->days_per_year !== null
            && $type->service_bonus_days > 0
            && $type->service_bonus_every_years > 0;

        return self::entitlementFrom(
            $type,
            $needsService ? self::serviceStarts(collect([$employee]))[$employee->id] : null,
            $asOf,
        );
    }

    /**
     * Entitlement given an already-resolved service start. The service bonus
     * implements Art. 77: the base days for the first year, plus bonus days
     * per every additional block of service years, counted from CUMULATIVE
     * service in the organization (16 + 1 per 2 extra years for annual leave:
     * service years 1–2 → 16, 3–4 → 17, 5–6 → 18, …).
     */
    public static function entitlementFrom(LeaveType $type, ?CarbonImmutable $serviceStart, CarbonImmutable $asOf): ?float
    {
        if ($type->days_per_year === null) {
            return null;
        }

        $entitled = (float) $type->days_per_year;

        if ($type->service_bonus_days > 0 && $type->service_bonus_every_years > 0 && $serviceStart !== null) {
            $years = (int) $serviceStart->diffInYears($asOf);
            $entitled += intdiv(max(0, $years), $type->service_bonus_every_years) * $type->service_bonus_days;
        }

        return $entitled;
    }

    /**
     * Cumulative service start per employee: the earliest hire date across
     * EVERY HR file the school holds for the same person, so a branch move or
     * rehire (a new per-branch employees row) never resets the Art. 77 clock.
     * Employee rows without a linked account fall back to their own positions.
     *
     * @param  Collection<int, Employee>  $employees  with `positions` loaded
     * @return array<int, CarbonImmutable|null> employee id → service start
     */
    public static function serviceStarts(Collection $employees): array
    {
        $linked = $employees->whereNotNull('user_id');

        $schoolWide = $linked->isEmpty() ? collect() : EmployeePosition::query()
            ->join('employees', 'employees.id', '=', 'employee_positions.employee_id')
            ->whereNull('employees.deleted_at')
            ->whereIn('employees.school_id', $linked->pluck('school_id')->unique())
            ->whereIn('employees.user_id', $linked->pluck('user_id')->unique())
            ->whereNotNull('employee_positions.hired_on')
            ->groupBy('employees.school_id', 'employees.user_id')
            ->selectRaw('employees.school_id, employees.user_id, min(employee_positions.hired_on) as hired_on')
            ->get()
            ->keyBy(fn ($row) => $row->school_id.':'.$row->user_id);

        return $employees
            ->mapWithKeys(function (Employee $employee) use ($schoolWide): array {
                $earliest = $employee->user_id !== null
                    ? $schoolWide->get($employee->school_id.':'.$employee->user_id)?->hired_on
                    : $employee->positions->pluck('hired_on')->filter()->min();

                return [$employee->id => $earliest === null
                    ? null
                    : CarbonImmutable::parse($earliest instanceof CarbonInterface ? $earliest->toDateString() : $earliest)];
            })
            ->all();
    }
}
