<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;

class NotificationsPurgeReadCommand extends Command
{
    protected $signature = 'notifications:purge-read';
    protected $description = 'Purge read notifications older than 90 days';

    public function handle()
    {
        $deleted = Notification::where('read_at', '<', now()->subDays(90))
            ->whereNotNull('read_at')
            ->delete();

        $this->info("Purged {$deleted} read notifications older than 90 days.");
        return 0;
    }
}
