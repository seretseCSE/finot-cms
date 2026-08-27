<?php

namespace App\Services\Tutoring;

use App\Enums\CycleStatus;
use App\Enums\EngagementStatus;
use App\Models\TutoringCycle;
use App\Models\TutoringEngagement;
use App\Support\BillingPeriod;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;

/**
 * Creates the Ethiopian-month escrow cycles. Idempotent on
 * (engagement, ec_year, ec_month) — the same anchor recurring fee billing
 * uses — so re-runs never double-bill. Credit carried out of the previous
 * released cycle is applied to the new cycle's amount_due; a fully-credited
 * month funds itself without a payment.
 */
class CycleBiller
{
    /** Ensure the cycle covering $date exists (default: today, Addis time). */
    public function ensureCycleFor(TutoringEngagement $engagement, ?CarbonImmutable $date = null): ?TutoringCycle
    {
        if ($engagement->status !== EngagementStatus::Active) {
            return null;
        }

        $date ??= CarbonImmutable::now('Africa/Addis_Ababa');
        $ec = EthiopianDate::fromGregorian($date);

        // Pagume (the 5–6 day 13th month) folds into the new year's Meskerem.
        if ($ec['month'] === 13) {
            $ec = ['year' => $ec['year'] + 1, 'month' => 1, 'day' => 1];
        }

        return $this->ensurePeriodCycle($engagement, $ec['year'], $ec['month']);
    }

    public function ensurePeriodCycle(TutoringEngagement $engagement, int $ecYear, int $ecMonth): TutoringCycle
    {
        $existing = TutoringCycle::query()
            ->where('engagement_id', $engagement->id)
            ->where('ec_year', $ecYear)
            ->where('ec_month', $ecMonth)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $period = BillingPeriod::make($ecYear, $ecMonth, 1, 1);
        $plannedHours = $engagement->plannedMonthlyHours();
        $gross = round($plannedHours * (float) $engagement->hourly_rate, 2);

        // Unused value from the last released cycle rolls in as credit.
        $credit = min($gross, $this->availableCredit($engagement));
        $due = round($gross - $credit, 2);

        $cycle = TutoringCycle::create([
            'engagement_id' => $engagement->id,
            'ec_year' => $ecYear,
            'ec_month' => $ecMonth,
            'label' => $period->label,
            'starts_on' => $period->start,
            'ends_on' => $period->end,
            'planned_hours' => $plannedHours,
            'hourly_rate' => $engagement->hourly_rate,
            'gross_amount' => $gross,
            'credit_applied' => $credit,
            'amount_due' => $due,
            'commission_percent' => $engagement->commission_percent,
            'status' => $due <= 0 ? CycleStatus::Funded->value : CycleStatus::AwaitingPayment->value,
            'funded_at' => $due <= 0 ? now() : null,
        ]);

        if ($credit > 0) {
            $this->consumeCredit($engagement, $credit);
        }

        return $cycle;
    }

    /** Credit = the latest released cycle's carried value not yet consumed. */
    private function availableCredit(TutoringEngagement $engagement): float
    {
        return (float) TutoringCycle::query()
            ->where('engagement_id', $engagement->id)
            ->where('status', CycleStatus::Released->value)
            ->sum('credit_carried');
    }

    private function consumeCredit(TutoringEngagement $engagement, float $amount): void
    {
        // Consume oldest-first; credit_carried on released cycles is the bank.
        $cycles = TutoringCycle::query()
            ->where('engagement_id', $engagement->id)
            ->where('status', CycleStatus::Released->value)
            ->where('credit_carried', '>', 0)
            ->orderBy('ends_on')
            ->get();

        foreach ($cycles as $cycle) {
            if ($amount <= 0) {
                break;
            }

            $take = min((float) $cycle->credit_carried, $amount);
            $cycle->update(['credit_carried' => round((float) $cycle->credit_carried - $take, 2)]);
            $amount = round($amount - $take, 2);
        }
    }
}
