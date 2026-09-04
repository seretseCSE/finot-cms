<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * The feed is delivery state, not history (that's activity_logs + the domain
 * tables): read rows go after 90 days, unread after 180 — an alert nobody
 * opened for six months is noise, and an unbounded feed table is a perf bug.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete read notifications older than 90 days and unread ones older than 180.';

    public function handle(): int
    {
        $read = Notification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        $unread = Notification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays(180))
            ->delete();

        $this->info("Pruned {$read} read and {$unread} unread notifications.");

        return self::SUCCESS;
    }
}
