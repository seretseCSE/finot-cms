<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One billing period of a recurring fee: an Ethiopian month (or a run of
 * them, for quarterly/semester/yearly strides) with its Gregorian window,
 * due date and human label. `year`/`month` identify the period's FIRST
 * Ethiopian month — the pair the invoice's idempotency key is built on.
 */
final readonly class BillingPeriod
{
    public function __construct(
        public int $year,
        public int $month,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $due,
        public string $label,
    ) {
    }

    /**
     * Build the period starting at Ethiopian ($year, $month) spanning
     * $stride months, due on the $billingDay of its first month.
     */
    public static function make(int $year, int $month, int $stride, int $billingDay): self
    {
        $last = EthiopianDate::addMonths($year, $month, $stride - 1);

        $label = match (true) {
            $stride >= 12 => (string) $year,
            $stride === 1 => EthiopianDate::monthLabel($year, $month),
            $last['year'] === $year => EthiopianDate::MONTHS[$month].'–'.EthiopianDate::monthLabel($last['year'], $last['month']),
            default => EthiopianDate::monthLabel($year, $month).' – '.EthiopianDate::monthLabel($last['year'], $last['month']),
        };

        return new self(
            year: $year,
            month: $month,
            start: EthiopianDate::monthStart($year, $month),
            end: EthiopianDate::monthEnd($last['year'], $last['month']),
            due: EthiopianDate::toGregorian($year, $month, min($billingDay, EthiopianDate::daysInMonth($year, $month))),
            label: $label,
        );
    }
}
