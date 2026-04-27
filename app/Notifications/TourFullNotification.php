<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TourFullNotification extends Notification
{
    use Queueable;

    public function __construct(string $tourName, string $tourDate)
    {
        $this->tourName = $tourName;
        $this->tourDate = $tourDate;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tour to ' . $this->tourName . ' is now full',
            'message' => 'Tour to ' . $this->tourName . ' on ' . $this->tourDate . ' has reached maximum capacity',
            'action_url' => '/tours',
        ];
    }
}
