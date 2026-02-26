<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OutstandingContribution extends Notification
{
    use Queueable;

    public function __construct(
        public $contributions,
        public $totalAmount
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Outstanding Contributions Notification')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You have ' . $this->contributions->count() . ' outstanding contributions totaling ' . number_format($this->totalAmount, 2) . ' ETB.')
            ->action('View Contributions', url('/admin/contributions'))
            ->line('Please settle these contributions at your earliest convenience.');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Outstanding Contributions',
            'message' => 'You have ' . $this->contributions->count() . ' outstanding contributions',
            'amount' => $this->totalAmount,
            'url' => '/admin/contributions',
        ];
    }
}
