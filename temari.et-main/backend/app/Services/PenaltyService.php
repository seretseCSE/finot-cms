<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Ethiopia;
use Carbon\CarbonImmutable;

/**
 * Accrues late penalties onto overdue invoices from their fee's penalty
 * config: `fixed` = one flat amount once the due date passes; `incremental`
 * = +amount for every `penalty_increment_days` elapsed past due. The accrual
 * is a pure recompute from (due_date, today) — re-running never double
 * charges — and stops the moment an invoice is settled, voided, covered by
 * scholarship, or its penalty is explicitly waived.
 */
class PenaltyService
{
    /** Recompute penalties on every eligible invoice; returns rows changed. */
    public function apply(?CarbonImmutable $today = null): int
    {
        $today = $today ?? CarbonImmutable::parse(Ethiopia::today());
        $changed = 0;

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->where('penalty_waived', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereHas('feeStructure', fn ($q) => $q->whereNotNull('penalty_type'))
            ->with('feeStructure')
            ->chunkById(500, function ($invoices) use ($today, &$changed): void {
                foreach ($invoices as $invoice) {
                    $penalty = $this->penaltyFor($invoice, $today);

                    if ($penalty === null || abs($penalty - (float) $invoice->penalty_amount) < 0.005) {
                        continue;
                    }

                    $invoice->penalty_amount = number_format($penalty, 2, '.', '');
                    $invoice->save();
                    $changed++;
                }
            });

        return $changed;
    }

    /** The penalty this invoice should carry as of $today (null = no config). */
    public function penaltyFor(Invoice $invoice, CarbonImmutable $today): ?float
    {
        $fee = $invoice->feeStructure;

        if ($fee === null || $fee->penalty_type === null || (float) $fee->penalty_amount <= 0) {
            return null;
        }

        $daysPast = (int) $invoice->due_date->toImmutable()->startOfDay()
            ->diffInDays($today->startOfDay());

        if ($daysPast <= 0) {
            return 0.0;
        }

        return match ($fee->penalty_type) {
            'fixed' => (float) $fee->penalty_amount,
            'incremental' => (float) $fee->penalty_amount
                * intdiv($daysPast, max(1, (int) $fee->penalty_increment_days ?: 1)),
            default => null,
        };
    }
}
