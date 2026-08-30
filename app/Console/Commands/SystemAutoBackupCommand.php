<?php

namespace App\Console\Commands;

use App\Services\SystemZipBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemAutoBackupCommand extends Command
{
    protected $signature = 'backup:auto';

    protected $description = 'Create a daily automatic system backup and keep the newest 30';

    public function handle(SystemZipBackupService $backups): int
    {
        $this->info('Starting automatic system backup...');

        try {
            $result = $backups->create('auto');
            $this->info("Created {$result['filename']}.");

            Log::channel('audit')->info('Tier 1 Audit Log', [
                'tier' => 1,
                'action' => 'auto_backup_created',
                'entity_type' => 'system',
                'new_value' => json_encode([
                    'filename' => $result['filename'],
                    'size' => $result['size'],
                ]),
                'user_id' => null,
                'timestamp' => now()->toDateTimeString(),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Automatic backup failed: '.$e->getMessage());

            Log::error('Automatic backup failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
