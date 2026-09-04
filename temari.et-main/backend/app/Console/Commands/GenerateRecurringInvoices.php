<?php

namespace App\Console\Commands;

use App\Services\RecurringBillingService;
use Illuminate\Console\Command;

/**
 * Daily scheduler entry for the recurring billing engine: issues every due
 * Ethiopian-month period of every auto-generating recurring fee. Idempotent —
 * re-runs only fill gaps (new periods, new mid-year enrollees).
 */
class GenerateRecurringInvoices extends Command
{
    protected $signature = 'fees:generate-recurring';

    protected $description = 'Issue invoices for every due billing period of auto-generating recurring fees';

    public function handle(RecurringBillingService $billing): int
    {
        $totals = $billing->run();

        $this->info(sprintf(
            'Recurring billing: %d fees, %d periods checked, %d invoices created.',
            $totals['fees'],
            $totals['periods'],
            $totals['invoices'],
        ));

        return self::SUCCESS;
    }
}
