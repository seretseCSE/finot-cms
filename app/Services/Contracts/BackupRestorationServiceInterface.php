<?php

namespace App\Services\Contracts;

use App\Models\SystemBackup;

interface BackupRestorationServiceInterface
{
    /**
     * Restore from backup.
     */
    public function restoreBackup(SystemBackup $backup): void;
}
