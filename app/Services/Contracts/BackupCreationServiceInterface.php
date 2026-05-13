<?php

namespace App\Services\Contracts;

use App\Models\SystemBackup;

interface BackupCreationServiceInterface
{
    /**
     * Create a new system backup.
     */
    public function createBackup(int $userId): SystemBackup;
}
