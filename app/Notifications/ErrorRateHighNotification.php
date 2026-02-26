<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ErrorRateHighNotification extends Notification
{
    use Queueable;

    public function __construct(string $errorRate)
    {
        $this->errorRate = $errorRate;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Error Rate High',
            'message' => 'Error rate: ' . $this->errorRate . '/hr detected',
            'action_url' => '/system/health',
        ];
    }
}
