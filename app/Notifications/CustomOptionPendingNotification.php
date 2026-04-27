<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomOptionPendingNotification extends Notification
{
    use Queueable;

    public function __construct(string $optionName)
    {
        $this->optionName = $optionName;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Custom Option Pending Approval',
            'message' => 'Custom dropdown option "' . $this->optionName . '" is pending admin approval',
            'action_url' => '/admin/custom-options',
        ];
    }
}
