<?php

namespace App\Console\Commands;

use App\Enums\CycleStatus;
use App\Models\TutoringCycle;
use App\Services\Notify\Notifier;
use App\Services\Tutoring\CycleReleaser;
use App\Support\Marketplace;
use Illuminate\Console\Command;

/**
 * Auto-release, OFF by default: when the operator sets
 * marketplace.auto_release_days (N), funded cycles whose month ended N+
 * days ago with every session decided release themselves. Launch mode is
 * manual — Temari.et staff release from the money console.
 */
class ReleaseTutoringCycles extends Command
{
    protected $signature = 'tutoring:release-due';

    protected $description = 'Auto-release settled tutoring cycles when auto-release is enabled';

    public function handle(CycleReleaser $releaser, Notifier $notifier): int
    {
        $days = Marketplace::settings()['auto_release_days'];

        if ($days === null) {
            $this->info('Auto-release is off (manual mode).');

            return self::SUCCESS;
        }

        $due = TutoringCycle::query()
            ->where('status', CycleStatus::Funded->value)
            ->where('ends_on', '<=', now('Africa/Addis_Ababa')->subDays($days)->toDateString())
            ->whereDoesntHave('sessions', fn ($s) => $s->whereIn('status', ['logged', 'disputed']))
            ->with('engagement.tutorProfile.user')
            ->limit(200)
            ->get();

        $released = 0;

        foreach ($due as $cycle) {
            $cycle = $releaser->release($cycle);
            $released++;

            $notifier->toUser($cycle->engagement?->tutorProfile?->user, 'tutoring.cycle_released', [
                'label' => $cycle->label,
                'amount' => (string) $cycle->released_amount,
            ], ['link' => '/tutoring/earnings']);
        }

        $this->info("Released {$released} cycles.");

        return self::SUCCESS;
    }
}
