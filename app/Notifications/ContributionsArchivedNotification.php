<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContributionsArchivedNotification extends Notification
{
    use Queueable;

    public function __construct(string $year, string $archiveDate)
    {
        $this->year = $year;
        $this->archiveDate = $archiveDate;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Contributions Archived',
            'message' => 'Contributions for ' . $this->year . ' have been archived on ' . $this->archiveDate,
            'action_url' => '/contributions',
        ];
    }
}
