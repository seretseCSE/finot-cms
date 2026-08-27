<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Services\FeeReminderService;
use App\Support\Ethiopia;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fans one branch's automated fee reminders out in the background — a branch
 * can hold thousands of open invoices and SMS/mail round-trips must never
 * block the scheduler tick. Safe to re-dispatch: the invoice_reminders
 * ledger dedupes every (invoice, recipient, stage, channel).
 */
class SendFeeReminders implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $branchId) {}

    public function handle(FeeReminderService $reminders): void
    {
        $branch = Branch::with('school')->find($this->branchId);
        if ($branch === null) {
            return;
        }

        $sent = $reminders->runForBranch($branch, CarbonImmutable::parse(Ethiopia::today()));

        if ($sent['sms'] > 0 || $sent['email'] > 0) {
            Log::info('Fee reminders sent.', ['branch_id' => $branch->id, ...$sent]);
        }
    }
}
