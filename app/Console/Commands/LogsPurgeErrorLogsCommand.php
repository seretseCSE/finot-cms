<?php

namespace App\Console\Commands;

use App\Models\ErrorLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogsPurgeErrorLogsCommand extends Command
{
    protected $signature = 'logs:purge-error-logs';

    protected $description = 'Purge error logs older than 2 months';

    public function handle(): int
    {
        $this->info('Starting error log purge...');

        $cutoffDate = now()->subMonths(2);
        $deletedCount = ErrorLog::where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Purged {$deletedCount} error logs older than 2 months.");

        // Log to Tier 1 audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'error_logs_purged',
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
