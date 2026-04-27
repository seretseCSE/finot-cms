<?php

namespace App\Jobs;

use App\Models\SystemBackup;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public function handle(BackupService $backupService): void
    {
        $backup = SystemBackup::findOrFail($this->backupId);

        try {
            $backup->update(['status' => 'in_progress']);

            $backupService->performBackup($backup);

            $size = Storage::disk('backups')->size($backup->filename);

            $backup->update([
                'size' => $size,
                'status' => 'completed',
                'completed_at' => now(),
                'log_message' => 'Backup completed successfully',
            ]);

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
