<?php

namespace App\Console\Commands;

use App\Jobs\SendFeeReminders;
use App\Models\Branch;
use Illuminate\Console\Command;

/**
 * Daily scheduler entry for the fee-reminder ladder: queues one job per
 * active branch that has reminders enabled. The ledger makes re-runs no-ops.
 */
class SendFeeRemindersCommand extends Command
{
    protected $signature = 'fees:send-reminders {--branch= : Only this branch id}';

    protected $description = 'Queue the automated fee payment reminders (upcoming / due / overdue ladder) per branch';

    public function handle(): int
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->when($this->option('branch'), fn ($q, $id) => $q->whereKey($id))
            ->whereHas('school', fn ($q) => $q->where('is_active', true))
            ->with('school')
            ->get();

        $queued = 0;

        foreach ($branches as $branch) {
            if (! $branch->effectiveFeeRemindersEnabled()) {
                continue;
            }

            SendFeeReminders::dispatch($branch->id);
            $queued++;
        }

        $this->info("Fee reminders queued for {$queued} branches.");

        return self::SUCCESS;
    }
}
