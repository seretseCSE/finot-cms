<?php

namespace App\Notifications;

use App\Helpers\EthiopianDateHelper;
use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SessionLockReminder extends Notification
{
    use Queueable;

    public function __construct(
        public AttendanceSession $session,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'session_lock_reminders';
    }

    public function toArray(object $notifiable): array
    {
        $helper = app(EthiopianDateHelper::class);
        $sessionDate = $helper->toString($this->session->session_date);

        $classNames = $this->session->classes->pluck('name')->join(', ') ?: 'N/A';

        return [
            'title' => 'Session Lock Reminder / ክፍለ ጊዜ ይዘጋል',
            'message' => "Attendance session for {$classNames} on {$sessionDate} will auto-lock in 3 days",
            'action_url' => route('filament.admin.resources.attendance-sessions.index'),
            'session_id' => $this->session->getKey(),
            'class_names' => $classNames,
            'session_date' => $this->session->session_date,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage())
            ->subject($data['title'])
            ->line($data['message'])
            ->action('View Sessions', $data['action_url']);
    }
}
