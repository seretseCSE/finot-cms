<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DatabaseSlowNotification extends Notification
{
    use Queueable;

    public function __construct(string $responseTime)
    {
        $this->responseTime = $responseTime;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Database Slow Response',
            'message' => 'Database response time of ' . $this->responseTime . 'ms detected',
            'action_url' => '/system/health',
        ];
    }
}
