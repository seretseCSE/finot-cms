<?php

namespace App\Console\Commands;

use App\Models\SystemBackup;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RestoreBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore
                            {backup : The ID of the backup to restore}
                            {--force : Run the restore synchronously instead of queueing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the system from a backup';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $backupId = $this->argument('backup');
        $force = $this->option('force');

        $backup = SystemBackup::find($backupId);

        if (! $backup) {
            $this->error("Backup with ID {$backupId} not found.");

            return self::FAILURE;
        }

        if (! $backup->canBeRestored()) {
            $this->error('Backup cannot be restored (status is not completed or file is missing).');

            return self::FAILURE;
        }

        if ($force) {
            $this->warn('Running restore synchronously...');

            try {
                $backupService->restoreBackup($backup, true);
                $this->info('Backup restored successfully.');

                return self::SUCCESS;
            } catch (\Exception $e) {
                $this->error('Restore failed: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $backupService->restoreBackup($backup, false);
        $this->info('Restore job dispatched to the queue.');

        return self::SUCCESS;
    }
}
