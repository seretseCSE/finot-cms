<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Notifications\SessionLockReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class AttendanceSendLockRemindersCommand extends Command
{
    protected $signature = 'attendance:send-lock-reminders';

    protected $description = 'Send in-app notifications for sessions that will auto-lock in 3 days';

    public function handle(): int
    {
        $this->info('Sending session lock reminders...');

        $thresholdDate = now()->subDays(3);
        $sessions = AttendanceSession::query()
            ->where('status', 'Open')
            ->where('session_date', '=', $thresholdDate)
            ->with(['class', 'academicYear'])
            ->get();

        $sentCount = 0;

        foreach ($sessions as $session) {
            // Find Education Monitor assigned to this class (simplified: any user with education_monitor role)
            $monitors = \App\Models\User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'education_monitor'))
                ->get();

            foreach ($monitors as $monitor) {
                Notification::send($monitor, new SessionLockReminder($session));
            }

            $sentCount++;
        }

        $this->info("Sent lock reminders for {$sentCount} sessions.");

        return $sentCount;
    }
}
