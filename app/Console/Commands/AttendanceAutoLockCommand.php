<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AttendanceAutoLockCommand extends Command
{
    protected $signature = 'attendance:auto-lock';

    protected $description = 'Auto-lock attendance sessions older than 30 days';

    public function handle(): int
    {
        $this->info('Starting auto-lock for attendance sessions...');

        $sessions = AttendanceSession::query()
            ->whereIn('status', ['Open', 'Completed'])
            ->where('session_date', '<', now()->subDays(30))
            ->get();

        $lockedCount = 0;

        foreach ($sessions as $session) {
            $session->update([
                'status' => 'Locked',
                'locked_at' => now(),
                'locked_by' => null, // system auto-lock
            ]);

            $lockedCount++;

            Log::channel('audit')->info('Tier 1 Audit Log', [
                'tier' => 1,
                'action' => 'auto_locked',
                'entity' => 'attendance_session',
                'session_id' => $session->getKey(),
                'academic_year_id' => $session->academic_year_id,
                'class_id' => $session->class_id,
                'session_date' => $session->session_date,
                'performed_by' => 'system',
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        $this->info("Auto-locked {$lockedCount} attendance sessions.");

        return $lockedCount;
    }
}
