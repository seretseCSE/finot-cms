<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StorageHighUsageNotification extends Notification
{
    use Queueable;

    public function __construct(string $usagePercentage)
    {
        $this->usagePercentage = $usagePercentage;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Storage Usage High',
            'message' => 'Storage usage at ' . $this->usagePercentage . '% - consider cleanup',
            'action_url' => '/system/health',
        ];
    }
}
