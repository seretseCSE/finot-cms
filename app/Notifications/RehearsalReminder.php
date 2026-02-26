<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RehearsalReminder extends Notification
{
    use Queueable;

    public function __construct(
        public $rehearsal
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Rehearsal Reminder')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('This is a reminder for your rehearsal tomorrow:')
            ->line('Date: ' . $this->rehearsal->scheduled_at->toFormattedDateString())
            ->line('Time: ' . $this->rehearsal->start_time)
            ->action('View Details', url('/admin/rehearsals/' . $this->rehearsal->id))
            ->line('Please be on time and prepared.');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Rehearsal Reminder',
            'message' => 'Rehearsal scheduled for ' . $this->rehearsal->scheduled_at->toFormattedDateString(),
            'time' => $this->rehearsal->start_time,
            'url' => '/admin/rehearsals/' . $this->rehearsal->id,
        ];
    }
}
