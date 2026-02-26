<?php

namespace App\Console\Commands;

use App\Models\AidDistribution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AidAutoLockCommand extends Command
{
    protected $signature = 'aid:auto-lock';

    protected $description = 'Auto-lock aid distributions older than 30 days';

    public function handle(): int
    {
        $this->info('Starting auto-lock process for aid distributions...');

        $cutoffDate = now()->subDays(30);
        $distributionsToLock = AidDistribution::where('created_at', '<', $cutoffDate)
            ->where('is_locked', false)
            ->get();

        $lockedCount = 0;
        foreach ($distributionsToLock as $distribution) {
            $distribution->update(['is_locked' => true, 'locked_at' => now()]);
            $lockedCount++;

            // Log to audit trail
            Log::channel('audit')->info('Tier 1 Audit Log', [
                'tier' => 1,
                'action' => 'aid_distribution_auto_locked',
                'entity_id' => $distribution->id,
                'entity_type' => 'aid_distribution',
                'old_value' => json_encode(['is_locked' => false]),
                'new_value' => json_encode(['is_locked' => true]),
                'user_id' => null, // System action
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        $this->info("Auto-locked {$lockedCount} aid distributions older than 30 days.");

        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'aid_auto_lock_executed',
            'entity_id' => null,
            'entity_type' => 'system',
            'old_value' => null,
            'new_value' => json_encode(['distributions_locked' => $lockedCount]),
            'user_id' => null,
            'timestamp' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }
}
