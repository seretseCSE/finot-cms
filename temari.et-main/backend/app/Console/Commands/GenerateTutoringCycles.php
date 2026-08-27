<?php

namespace App\Console\Commands;

use App\Enums\CycleStatus;
use App\Enums\EngagementStatus;
use App\Models\TutoringEngagement;
use App\Services\Notify\Notifier;
use App\Services\Tutoring\CycleBiller;
use Illuminate\Console\Command;

/**
 * Daily scheduler entry: every active engagement gets its current
 * Ethiopian-month escrow cycle (idempotent on engagement × EC period) and
 * the family is billed by notification. Cheap when nothing is due.
 */
class GenerateTutoringCycles extends Command
{
    protected $signature = 'tutoring:generate-cycles';

    protected $description = 'Create the current Ethiopian-month escrow cycle for every active tutoring engagement';

    public function handle(CycleBiller $biller, Notifier $notifier): int
    {
        $created = 0;

        TutoringEngagement::query()
            ->where('status', EngagementStatus::Active->value)
            ->with(['payer', 'tutorProfile.user'])
            ->chunkById(100, function ($engagements) use ($biller, $notifier, &$created): void {
                foreach ($engagements as $engagement) {
                    $cycle = $biller->ensureCycleFor($engagement);

                    if ($cycle === null || ! $cycle->wasRecentlyCreated) {
                        continue;
                    }

                    $created++;

                    if ($cycle->status === CycleStatus::AwaitingPayment) {
                        $notifier->toUser($engagement->payer, 'tutoring.cycle_due', [
                            'label' => $cycle->label,
                            'amount' => (string) $cycle->amount_due,
                            'tutor' => (string) $engagement->tutorProfile?->user?->name,
                        ], ['link' => '/me/tutoring', 'dedupeKey' => 'tutoring.cycle_due:'.$cycle->id]);
                    }
                }
            });

        $this->info("Created {$created} tutoring cycles.");

        return self::SUCCESS;
    }
}
