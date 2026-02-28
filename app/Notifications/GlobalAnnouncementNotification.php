<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Filament\Notifications\Notification as FilamentNotification;

class GlobalAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $announcement;
    protected $channels;

    /**
     * Create a new notification instance.
     */
    public function __construct(Announcement $announcement, array $channels = ['database'])
    {
        $this->announcement = $announcement;
        $this->channels = $channels;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->announcement->is_urgent ? '🚨 URGENT: ' . $this->announcement->title : '📢 Global Announcement: ' . $this->announcement->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new global announcement has been published:')
            ->line('**' . $this->announcement->title . '**')
            ->line($this->announcement->content)
            ->action('View Announcement', url('/admin/announcements/' . $this->announcement->id))
            ->line('This is an important system-wide announcement that requires your attention.')
            ->salutation('Thank you for your cooperation.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => $this->announcement->title,
            'content' => $this->announcement->content,
            'announcement_id' => $this->announcement->id,
            'is_urgent' => $this->announcement->is_urgent,
            'is_global' => $this->announcement->is_global,
            'target_audience' => $this->announcement->target_audience,
            'created_at' => $this->announcement->created_at->toDateTimeString(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'content' => $this->announcement->content,
            'is_urgent' => $this->announcement->is_urgent,
            'is_global' => $this->announcement->is_global,
            'target_audience' => $this->announcement->target_audience,
        ];
    }

    /**
     * Send Filament notification for real-time display
     */
    public function toFilament(object $notifiable): FilamentNotification
    {
        $notification = FilamentNotification::make()
            ->title($this->announcement->is_urgent ? '🚨 Urgent Global Announcement' : '📢 Global Announcement')
            ->body($this->announcement->title)
            ->success();

        if ($this->announcement->is_urgent) {
            $notification->danger();
        }

        return $notification;
    }

    /**
     * Determine which users should receive this notification
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Don't send to users who have already acknowledged
        if ($this->announcement->isAcknowledgedBy($notifiable->id)) {
            return false;
        }

        // Check target audience
        return match($this->announcement->target_audience) {
            'all_users' => true,
            'admin_only' => $notifiable->hasRole(['admin', 'superadmin']),
            'department_heads' => str_contains($notifiable->role, '_head'),
            'specific_departments' => in_array($notifiable->department_id, $this->announcement->specific_departments ?? []),
            'specific_roles' => in_array($notifiable->role, $this->announcement->specific_roles ?? []),
            default => false,
        };
    }
}
