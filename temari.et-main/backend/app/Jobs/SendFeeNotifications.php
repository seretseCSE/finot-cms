<?php

namespace App\Jobs;

use App\Models\FeeStructure;
use App\Services\FeeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fans one fee's on-demand payment notices out in the background — a fee can
 * hold hundreds of open invoices and SMS/mail round-trips must never block
 * the finance officer's request.
 */
class SendFeeNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $feeStructureId,
        public bool $parents,
        public bool $students,
    ) {
    }

    public function handle(FeeNotifier $notifier): void
    {
        $feeStructure = FeeStructure::find($this->feeStructureId);
        if ($feeStructure === null) {
            return;
        }

        $sent = $notifier->send($feeStructure, $this->parents, $this->students);

        Log::info('Fee notifications sent.', [
            'fee_structure_id' => $feeStructure->id,
            ...$sent,
        ]);
    }
}
