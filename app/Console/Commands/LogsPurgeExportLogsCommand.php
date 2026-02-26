<?php

namespace App\Console\Commands;

use App\Models\ExportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogsPurgeExportLogsCommand extends Command
{
    protected $signature = 'logs:purge-export-logs';

    protected $description = 'Purge export logs older than 1 year';

    public function handle(): int
    {
        $this->info('Starting export log purge...');

        $cutoffDate = now()->subYear();
        $deletedCount = ExportLog::where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Purged {$deletedCount} export logs older than 1 year.");

        // Log to Tier 1 audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'export_logs_purged',
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
