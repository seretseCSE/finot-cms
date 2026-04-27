<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceSyncConflict;
use App\Models\OfflineAttendanceSync;
use App\Models\StudentAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncService
{
    /**
     * Queue offline attendance records for sync.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function queueAttendanceSync(int $userId, array $records): array
    {
        $results = [];

        foreach ($records as $record) {
            $sync = OfflineAttendanceSync::create([
                'user_id' => $userId,
                'session_id' => $record['session_id'],
                'student_id' => $record['student_id'] ?? null,
                'member_id' => $record['member_id'] ?? null,
                'status' => $record['status'],
                'marked_at' => $record['marked_at'] ?? now(),
                'sync_status' => 'pending',
            ]);

            $results[] = $sync;
        }

        return $results;
    }

    /**
     * Process pending offline attendance syncs.
     */
    public function processPendingSyncs(): array
    {
        $pending = OfflineAttendanceSync::pending()->get();
        $results = ['synced' => 0, 'conflicts' => 0];

        foreach ($pending as $sync) {
            try {
                $this->processSync($sync);
                $results['synced']++;
            } catch (\Exception $e) {
                $sync->markAsConflict($e->getMessage());
                $results['conflicts']++;

                Log::error('Offline attendance sync conflict', [
                    'sync_id' => $sync->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Process a single offline attendance sync record.
     */
    protected function processSync(OfflineAttendanceSync $sync): void
    {
        $session = AttendanceSession::query()->find($sync->session_id);

        if (! $session) {
            throw new \RuntimeException('Attendance session not found.');
        }

        if ($session->status === 'Locked') {
            throw new \RuntimeException('Attendance session is locked.');
        }

        DB::transaction(function () use ($sync): void {
            if ($sync->student_id) {
                $this->syncStudentAttendance($sync);
            } elseif ($sync->member_id) {
                $this->syncMemberAttendance($sync);
            } else {
                throw new \RuntimeException('No student or member ID provided.');
            }

            $sync->markAsSynced();
        });
    }

    /**
     * Sync student attendance.
     */
    protected function syncStudentAttendance(OfflineAttendanceSync $sync): void
    {
        $existing = StudentAttendance::query()
            ->where('student_id', $sync->student_id)
            ->where('session_id', $sync->session_id)
            ->first();

        if ($existing) {
            // Check for conflict
            if ($existing->status !== $sync->status) {
                AttendanceSyncConflict::create([
                    'student_id' => $sync->student_id,
                    'session_id' => $sync->session_id,
                    'first_user_id' => $existing->marked_by,
                    'first_value' => $existing->status,
                    'first_synced_at' => $existing->marked_at,
                    'second_user_id' => $sync->user_id,
                    'second_value' => $sync->status,
                    'second_synced_at' => $sync->marked_at,
                ]);

                throw new \RuntimeException('Attendance conflict detected.');
            }

            $existing->update([
                'marked_by' => $sync->user_id,
                'marked_at' => $sync->marked_at,
            ]);
        } else {
            StudentAttendance::create([
                'student_id' => $sync->student_id,
                'session_id' => $sync->session_id,
                'status' => $sync->status,
                'marked_by' => $sync->user_id,
                'marked_at' => $sync->marked_at,
            ]);
        }
    }

    /**
     * Sync member attendance.
     */
    protected function syncMemberAttendance(OfflineAttendanceSync $sync): void
    {
        $existing = AttendanceRecord::query()
            ->where('member_id', $sync->member_id)
            ->where('event_type', 'session')
            ->where('event_date', $sync->session->session_date ?? now()->toDateString())
            ->first();

        if ($existing) {
            $existing->update([
                'status' => $sync->status,
                'updated_by' => $sync->user_id,
            ]);
        } else {
            AttendanceRecord::create([
                'member_id' => $sync->member_id,
                'event_type' => 'session',
                'event_date' => $sync->session->session_date ?? now()->toDateString(),
                'status' => $sync->status,
                'created_by' => $sync->user_id,
            ]);
        }
    }
}
