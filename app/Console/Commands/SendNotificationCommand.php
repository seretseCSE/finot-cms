<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendNotificationCommand extends Command
{
    protected $signature = 'notification:send {user} {message}';

    protected $description = 'Send notification to user';

    public function handle(): int
    {
        $user = $this->argument('user');
        $message = $this->argument('message');

        $userModel = User::where('email', $user)->first();

        if (!$userModel) {
            $this->error('User not found: ' . $user);
            return Command::FAILURE;
        }

        Notification::send($userModel, new \App\Notifications\SystemAlert($message));

        $this->info('Notification sent to ' . $user . ': ' . $message);

        return Command::SUCCESS;
    }
}
