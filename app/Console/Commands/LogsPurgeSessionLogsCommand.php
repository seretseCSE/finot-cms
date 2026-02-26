<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogsPurgeSessionLogsCommand extends Command
{
    protected $signature = 'logs:purge-session-logs';

    protected $description = 'Purge user session logs older than 90 days';

    public function handle(): int
    {
        $this->info('Starting session log purge...');

        $cutoffDate = now()->subDays(90);
        $deletedCount = DB::table('user_sessions')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Purged {$deletedCount} session logs older than 90 days.");

        // Log to Tier 1 audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'session_logs_purged',
            'entity_id' => null,
            'entity_type' => 'system',
            'old_value' => null,
            'new_value' => json_encode(['logs_purged' => $deletedCount]),
            'user_id' => null, // System action
            'timestamp' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }
}
