<?php

namespace App\Services;

use App\Actions\GenerateInvoicesAction;
use App\Enums\AcademicYearStatus;
use App\Models\FeeStructure;
use App\Support\BillingPeriod;
use App\Support\Ethiopia;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The recurring billing engine. Each run walks every auto-generating
 * monthly/quarterly fee of an ACTIVE academic year and issues the invoices
 * for every billing period that has started — periods are ETHIOPIAN months
 * (a school bills "Meskerem tuition", never "September tuition"). Semester
 * fees stay term-anchored and are generated per term as before. Generation
 * is idempotent per (fee, period, student), so the daily scheduler run only
 * ever fills gaps: new periods, and new mid-year enrollees joining an
 * already-billed period.
 */
class RecurringBillingService
{
    public function __construct(private readonly GenerateInvoicesAction $generator) {}

    /**
     * Bill every due period of every eligible fee. Returns counters for the
     * scheduler log / command output.
     *
     * @return array{fees: int, periods: int, invoices: int}
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today = $today ?? CarbonImmutable::parse(Ethiopia::today());

        $fees = FeeStructure::query()
            ->where('is_active', true)
            ->where('auto_generate', true)
            ->whereIn('type', ['monthly', 'quarterly'])
            ->whereHas('academicYear', fn ($q) => $q->where('status', AcademicYearStatus::Active->value))
            // A deactivated branch (or school) stops billing immediately.
            ->whereHas('branch', fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('school', fn ($qq) => $qq->where('is_active', true)))
            ->with(['academicYear', 'branch.school'])
            ->get();

        $totals = ['fees' => 0, 'periods' => 0, 'invoices' => 0];

        foreach ($fees as $fee) {
            $periods = $this->duePeriods($fee, $today);
            if ($periods === []) {
                continue;
            }

            $totals['fees']++;

            foreach ($periods as $period) {
                $created = $this->generator->execute($fee, null, $period);
                $totals['periods']++;
                $totals['invoices'] += $created;

                if ($created > 0) {
                    Log::info('Recurring billing issued invoices.', [
                        'fee_structure_id' => $fee->id,
                        'period' => $period->label,
                        'created' => $created,
                    ]);
                }
            }
        }

        return $totals;
    }

    /**
     * Every billing period of this fee that has STARTED as of $today, oldest
     * first. The window is the fee's own (starts_on → due_on) clipped to its
     * academic year; periods are stamped from the Ethiopian month containing
     * the window start, stepping by the fee type's stride.
     *
     * @return list<BillingPeriod>
     */
    public function duePeriods(FeeStructure $fee, CarbonImmutable $today): array
    {
        $stride = $fee->monthStride();
        $year = $fee->academicYear;

        if ($stride === null || $year === null || $year->starts_on === null) {
            return [];
        }

        $windowStart = CarbonImmutable::parse(
            max($fee->starts_on?->toDateString() ?? '', $year->starts_on->toDateString()),
        );
        $windowEnd = CarbonImmutable::parse(
            $fee->due_on?->toDateString() ?? $year->ends_on?->toDateString() ?? $today->toDateString(),
        );

        if ($windowStart->greaterThan($windowEnd)) {
            return [];
        }

        // First period: the Ethiopian month holding the window start. Pagume
        // (month 13) is never a billing month — roll into the new year.
        $ec = EthiopianDate::fromGregorian($windowStart);
        if ($ec['month'] === 13) {
            $ec = ['year' => $ec['year'] + 1, 'month' => 1, 'day' => 1];
        }

        $limit = $today->lessThan($windowEnd) ? $today : $windowEnd;
        $periods = [];

        for ($y = $ec['year'], $m = $ec['month']; ; [$y, $m] = $this->step($y, $m, $stride)) {
            $period = BillingPeriod::make($y, $m, $stride, $fee->effectiveBillingDay());

            if ($period->start->greaterThan($limit)) {
                break;
            }

            $periods[] = $period;
        }

        return $periods;
    }

    /**
     * The NEXT billing periods of this fee (start strictly after $today),
     * oldest first — the family "upcoming payments" preview. Same window and
     * stride rules as duePeriods, capped at $max rows.
     *
     * @return list<BillingPeriod>
     */
    public function upcomingPeriods(FeeStructure $fee, CarbonImmutable $today, int $max = 3): array
    {
        $stride = $fee->monthStride();
        $year = $fee->academicYear;

        if ($stride === null || $year === null || $year->starts_on === null) {
            return [];
        }

        $windowStart = CarbonImmutable::parse(
            max($fee->starts_on?->toDateString() ?? '', $year->starts_on->toDateString()),
        );
        $windowEnd = CarbonImmutable::parse(
            $fee->due_on?->toDateString() ?? $year->ends_on?->toDateString() ?? $today->toDateString(),
        );

        if ($windowStart->greaterThan($windowEnd)) {
            return [];
        }

        $ec = EthiopianDate::fromGregorian($windowStart);
        if ($ec['month'] === 13) {
            $ec = ['year' => $ec['year'] + 1, 'month' => 1, 'day' => 1];
        }

        $periods = [];

        for ($y = $ec['year'], $m = $ec['month']; count($periods) < $max; [$y, $m] = $this->step($y, $m, $stride)) {
            $period = BillingPeriod::make($y, $m, $stride, $fee->effectiveBillingDay());

            if ($period->start->greaterThan($windowEnd)) {
                break;
            }

            if ($period->start->greaterThan($today)) {
                $periods[] = $period;
            }
        }

        return $periods;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function step(int $year, int $month, int $stride): array
    {
        $next = EthiopianDate::addMonths($year, $month, $stride);

        return [$next['year'], $next['month']];
    }
}
