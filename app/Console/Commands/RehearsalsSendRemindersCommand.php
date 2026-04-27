<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rehearsal;
use App\Models\User;
use App\Notifications\RehearsalReminder;

class RehearsalsSendRemindersCommand extends Command
{
    protected $signature = 'rehearsals:send-reminders';
    protected $description = 'Send rehearsal reminders';

    public function handle()
    {
        $tomorrow = now()->addDay()->toDateString();
        $rehearsals = Rehearsal::whereDate('scheduled_at', $tomorrow)->get();

        $sent = 0;
        foreach ($rehearsals as $rehearsal) {
            $participants = $rehearsal->participants ?? [];
            foreach ($participants as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new RehearsalReminder($rehearsal));
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} rehearsal reminders for tomorrow.");
        return 0;
    }
}
