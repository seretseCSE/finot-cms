<?php

namespace App\Services;

use App\Models\SystemBackup;
use App\Services\Contracts\BackupCleanupServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class BackupCleanupService implements BackupCleanupServiceInterface
{
    /**
     * Clean up old backups based on retention policy.
     * Keeps the last N completed backups, deletes older ones.
     */
    public function cleanupOldBackups(int $keepCount = 30): int
    {
        try {
            // Get completed backups ordered by creation date (newest first)
            $completedBackups = SystemBackup::completed()
                ->orderBy('created_at', 'desc')
                ->get();

            // If we have more than the retention limit, delete the oldest
            if ($completedBackups->count() > $keepCount) {
                $backupsToDelete = $completedBackups->slice($keepCount);
                $deletedCount = 0;

                foreach ($backupsToDelete as $backup) {
                    if ($this->deleteBackup($backup)) {
                        $deletedCount++;
                    }
                }

                Log::info("Backup cleanup completed", [
                    'deleted_count' => $deletedCount,
                    'retained_count' => $keepCount,
                ]);

                return $deletedCount;
            }

            return 0;

        } catch (Exception $e) {
            ExceptionHandlerService::handleServiceException($e, 'BackupCleanupService');
            return 0;
        }
    }

    /**
     * Delete a specific backup.
     */
    public function deleteBackup(SystemBackup $backup): bool
    {
        try {
            // Delete the file from storage
            if (Storage::disk('backups')->exists($backup->filename)) {
                Storage::disk('backups')->delete($backup->filename);
            }

            // Delete the database record
            $result = $backup->delete();

            Log::info('Backup deleted', ['backup_id' => $backup->id]);

            return $result;

        } catch (Exception $e) {
            ExceptionHandlerService::handleServiceException($e, 'BackupCleanupService');
            return false;
        }
    }

    /**
     * Delete failed backups.
     */
    public function deleteFailedBackups(): int
    {
        try {
            $failedBackups = SystemBackup::failed()->get();
            $deletedCount = 0;

            foreach ($failedBackups as $backup) {
                // Delete the file if it exists (failed backups might have partial files)
                if (Storage::disk('backups')->exists($backup->filename)) {
                    Storage::disk('backups')->delete($backup->filename);
                }

                // Delete the record
                if ($backup->delete()) {
                    $deletedCount++;
                }
            }

            if ($deletedCount > 0) {
                Log::info('Failed backups cleaned up', ['deleted_count' => $deletedCount]);
            }

            return $deletedCount;

        } catch (Exception $e) {
            ExceptionHandlerService::handleServiceException($e, 'BackupCleanupService');
            return 0;
        }
    }

    /**
     * Get storage usage information.
     */
    public function getStorageUsage(): array
    {
        try {
            $backups = SystemBackup::completed()->get();
            $totalSize = $backups->sum('size');
            $totalFiles = $backups->count();

            // Get disk usage
            $diskPath = Storage::disk('backups')->path('');
            $diskFree = disk_free_space($diskPath);
            $diskTotal = disk_total_space($diskPath);
            $diskUsed = $diskTotal - $diskFree;

            return [
                'total_backups' => $totalFiles,
                'total_size_bytes' => $totalSize,
                'total_size_human' => $this->formatBytes($totalSize),
                'disk_total_bytes' => $diskTotal,
                'disk_used_bytes' => $diskUsed,
                'disk_free_bytes' => $diskFree,
                'disk_usage_percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0,
            ];

        } catch (Exception $e) {
            ExceptionHandlerService::handleServiceException($e, 'BackupCleanupService');
            return [
                'total_backups' => 0,
                'total_size_bytes' => 0,
                'total_size_human' => '0 B',
                'disk_total_bytes' => 0,
                'disk_used_bytes' => 0,
                'disk_free_bytes' => 0,
                'disk_usage_percent' => 0,
            ];
        }
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
