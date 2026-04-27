<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewClassAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(string $className, string $teacherName)
    {
        $this->className = $className;
        $this->teacherName = $teacherName;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Class Assignment',
            'message' => 'You have been assigned to teach ' . $this->className . ' by ' . $this->teacherName,
            'action_url' => '/classes',
        ];
    }
}
