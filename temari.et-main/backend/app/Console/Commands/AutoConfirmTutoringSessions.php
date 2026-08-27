<?php

namespace App\Console\Commands;

use App\Enums\TutoringSessionStatus;
use App\Models\TutoringSession;
use App\Support\Marketplace;
use Illuminate\Console\Command;

/**
 * The Preply rule: a logged session the family neither confirms nor
 * disputes within Marketplace::AUTO_CONFIRM_HOURS auto-confirms — silence
 * never blocks a tutor's earnings.
 */
class AutoConfirmTutoringSessions extends Command
{
    protected $signature = 'tutoring:auto-confirm';

    protected $description = 'Auto-confirm logged tutoring sessions past the confirmation window';

    public function handle(): int
    {
        $count = TutoringSession::query()
            ->where('status', TutoringSessionStatus::Logged->value)
            ->where('logged_at', '<=', now()->subHours(Marketplace::AUTO_CONFIRM_HOURS))
            ->update([
                'status' => TutoringSessionStatus::Confirmed->value,
                'confirmed_at' => now(),
            ]);

        $this->info("Auto-confirmed {$count} sessions.");

        return self::SUCCESS;
    }
}
