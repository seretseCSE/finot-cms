<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TourRegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(string $tourName, string $tourDate, string $status)
    {
        $this->tourName = $tourName;
        $this->tourDate = $tourDate;
        $this->status = $status;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tour Registration ' . $this->status,
            'message' => 'Your registration for ' . $this->tourName . ' on ' . $this->tourDate . ' has been ' . strtolower($this->status),
            'action_url' => '/tours',
        ];
    }
}
