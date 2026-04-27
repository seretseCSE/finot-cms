<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TourCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(string $tourName, string $tourDate, string $reason)
    {
        $this->tourName = $tourName;
        $this->tourDate = $tourDate;
        $this->reason = $reason;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tour to ' . $this->tourName . ' on ' . $this->tourDate . ' has been cancelled',
            'message' => 'Tour to ' . $this->tourName . ' on ' . $this->tourDate . ' has been cancelled. Reason: ' . $this->reason,
            'action_url' => '/tours',
        ];
    }
}
