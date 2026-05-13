<?php

namespace App\Jobs;

use App\Models\SystemBackup;
use App\Services\BackupCreationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateBackupJob implements ShouldQueue
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

    public function handle(BackupCreationService $backupService): void
    {
        $backup = SystemBackup::findOrFail($this->backupId);

        try {
            // The createBackup method handles status updates internally
            $backupService->createBackup($backup->created_by);

            Log::info('Backup job completed successfully', ['backup_id' => $backup->id]);
        } catch (Throwable $e) {
            $backup->markAsFailed('Backup job failed: ' . $e->getMessage());

            Log::error('Backup job failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
