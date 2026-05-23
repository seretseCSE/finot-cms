<?php

namespace App\Console\Commands;

use App\Models\UserSession;
use Illuminate\Console\Command;

class SessionCleanupCommand extends Command
{
    protected $signature = 'session:cleanup';

    protected $description = 'Clean up expired user sessions older than the configured timeout';

    public function handle(): int
    {
        $timeout = config('finot.session_timeout_minutes', 30);

        $deleted = UserSession::where('last_activity', '<', now()->subMinutes($timeout))
            ->delete();

        $this->info("Cleaned up {$deleted} expired session(s).");

        return Command::SUCCESS;
    }
}
