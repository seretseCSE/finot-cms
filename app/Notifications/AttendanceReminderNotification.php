<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(string $className, string $rehearsalDate, string $rehearsalTime)
    {
        $this->className = $className;
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
            'title' => 'Attendance Reminder',
            'message' => 'Session for ' . $this->className . ' on ' . $this->rehearsalDate . ' at ' . $this->rehearsalTime . ' locks in 3 days',
            'action_url' => '/rehearsals',
        ];
    }
}
