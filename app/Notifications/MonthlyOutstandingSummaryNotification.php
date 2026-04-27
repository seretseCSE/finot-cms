<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MonthlyOutstandingSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(string $className, string $month, string $year, float $outstandingAmount)
    {
        $this->className = $className;
        $this->month = $month;
        $this->year = $year;
        $this->outstandingAmount = $outstandingAmount;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Monthly Outstanding Summary',
            'message' => $this->className . ' for ' . $this->month . ' ' . $this->year . ': ETB ' . number_format($this->outstandingAmount, 2) . ' outstanding',
            'action_url' => '/contributions',
        ];
    }
}
