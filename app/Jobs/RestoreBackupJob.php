<?php

namespace App\Jobs;

use App\Models\SystemBackup;
use App\Services\BackupRestorationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly int $backupId,
    ) {
    }

    public function handle(BackupRestorationService $backupService): void
    {
        $backup = SystemBackup::findOrFail($this->backupId);

        try {
            Log::info('Restore job started', ['backup_id' => $backup->id]);

            $backupService->restoreBackup($backup);

            Log::info('Restore job completed successfully', ['backup_id' => $backup->id]);
        } catch (Throwable $e) {
            Log::error('Restore job failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
