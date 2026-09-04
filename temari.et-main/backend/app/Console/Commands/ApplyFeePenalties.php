<?php

namespace App\Console\Commands;

use App\Services\PenaltyService;
use Illuminate\Console\Command;

/**
 * Daily scheduler entry for late-penalty accrual on overdue invoices.
 * A pure recompute — safe to re-run any number of times.
 */
class ApplyFeePenalties extends Command
{
    protected $signature = 'fees:apply-penalties';

    protected $description = 'Accrue late penalties on overdue invoices from their fee\'s penalty config';

    public function handle(PenaltyService $penalties): int
    {
        $changed = $penalties->apply();

        $this->info("Late penalties updated on {$changed} invoices.");

        return self::SUCCESS;
    }
}
