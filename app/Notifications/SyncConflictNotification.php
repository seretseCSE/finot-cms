<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SyncConflictNotification extends Notification
{
    use Queueable;

    public function __construct(string $className, string $syncDate)
    {
        $this->className = $className;
        $this->syncDate = $syncDate;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Sync Conflicts Detected',
            'message' => 'X sync conflicts detected for ' . $this->className . ' on ' . $this->syncDate,
            'action_url' => '/sync',
        ];
    }
}
