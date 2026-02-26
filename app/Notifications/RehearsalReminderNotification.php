<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RehearsalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(string $rehearsalDate, string $rehearsalTime)
    {
        $this->rehearsalDate = $rehearsalDate;
        $this->rehearsalTime = $rehearsalTime;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Rehearsal Reminder',
            'message' => 'Rehearsal scheduled for ' . $this->rehearsalDate . ' at ' . $this->rehearsalTime,
            'action_url' => '/rehearsals',
        ];
    }
}
