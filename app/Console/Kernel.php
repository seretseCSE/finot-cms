<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\AttendanceAutoLockCommand::class,
        \App\Console\Commands\AttendanceSendLockRemindersCommand::class,
        \App\Console\Commands\ContentPublishScheduledCommand::class,
        \App\Console\Commands\RehearsalsSendRemindersCommand::class,
        \App\Console\Commands\AidAutoLockCommand::class,
        \App\Console\Commands\SystemCheckHealthCommand::class,
        \App\Console\Commands\NotificationsPurgeReadCommand::class,
        \App\Console\Commands\LogsPurgeSecurityAuditCommand::class,
        \App\Console\Commands\LogsPurgeSessionLogsCommand::class,
        \App\Console\Commands\LogsPurgeErrorLogsCommand::class,
        \App\Console\Commands\LogsPurgeExportLogsCommand::class,
        \App\Console\Commands\FinanceNotifyOutstandingCommand::class,
        \App\Console\Commands\ScheduleTestCommand::class,
        \App\Console\Commands\MediaAutoArchiveCommand::class,
        \App\Console\Commands\BroadcastGlobalAnnouncementsCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Daily
        $schedule->command('attendance:auto-lock')->daily()
            ->description('Auto-lock attendance sessions older than 30 days');

        $schedule->command('attendance:send-lock-reminders')->daily()
            ->description('Send in-app notifications for sessions that will auto-lock in 3 days');

        $schedule->command('content:publish-scheduled')->daily()
            ->description('Publish scheduled content');

        $schedule->command('rehearsals:send-reminders')->daily()
            ->description('Send rehearsal reminders');

        $schedule->command('aid:auto-lock')->daily()
            ->description('Auto-lock aid records after cutoff');

        $schedule->command('notifications:purge-read')->daily()
            ->description('Purge read notifications older than 90 days');

        $schedule->command('logs:purge-security-audit')->daily()
            ->description('Purge security audit logs older than 30 days');

        $schedule->command('logs:purge-session-logs')->daily()
            ->description('Purge session logs older than 90 days');

        $schedule->command('logs:purge-error-logs')->daily()
            ->description('Purge error logs older than 2 months');

        $schedule->command('logs:purge-export-logs')->daily()
            ->description('Purge export logs older than 1 year');

        // Every 15 minutes
        $schedule->command('system:check-health')->everyFifteenMinutes()
            ->description('Check system health every 15 minutes');

        // Every 30 minutes for global announcements
        $schedule->command('announcements:broadcast-global')->everyThirtyMinutes()
            ->description('Broadcast active global announcements every 30 minutes');

        // Monthly
        $schedule->command('finance:notify-outstanding')->monthly()
            ->description('Notify outstanding contributions monthly');

        // Annually
        $schedule->command('media:auto-archive')->yearly()
            ->description('Archive media flags annually');
    }
}
