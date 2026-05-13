<?php

namespace App\Services\Contracts;

use App\Models\SystemBackup;

interface BackupCleanupServiceInterface
{
    /**
     * Clean up old backups based on retention policy.
     */
    public function cleanupOldBackups(int $keepCount = 30): int;

    /**
     * Delete a specific backup.
     */
    public function deleteBackup(SystemBackup $backup): bool;

    /**
     * Delete failed backups.
     */
    public function deleteFailedBackups(): int;

    /**
     * Get storage usage information.
     */
    public function getStorageUsage(): array;
}
