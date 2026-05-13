<?php

namespace App\Services\Contracts;

interface OfflineSyncServiceInterface
{
    /**
     * Queue offline attendance records for sync.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function queueAttendanceSync(int $userId, array $records): array;

    /**
     * Process pending offline attendance syncs.
     */
    public function processPendingSyncs(): array;
}
