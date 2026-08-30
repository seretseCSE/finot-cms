<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Content Workflow: Publish scheduled blog posts and announcements
Schedule::command('content:publish-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Daily full system backup (database + uploaded files)
Schedule::command('backup:auto')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-archive old records
Schedule::command('system:auto-archive')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-lock attendance sessions older than 30 days
Schedule::command('attendance:auto-lock')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-purge error logs older than 2 months
Schedule::command('logs:purge-error-logs')
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-purge session logs older than 3 months
Schedule::command('logs:purge-session-logs')
    ->weeklyOn(0, '04:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-purge security audit logs older than 1 year
Schedule::command('logs:purge-security-audit')
    ->monthlyOn(1, '05:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-purge export logs older than 6 months
Schedule::command('logs:purge-export-logs')
    ->monthlyOn(1, '05:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Automation: Auto-archive media items older than 5 years
Schedule::command('media:auto-archive')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// System Health Check
Schedule::command('system:check-health')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Session Cleanup: Purge expired sessions every 5 minutes (removed from request lifecycle)
Schedule::command('session:cleanup')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Dashboard Cache Warm: Pre-warm widget caches every 5 minutes for faster page loads
Schedule::command('dashboard:cache-warm')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
