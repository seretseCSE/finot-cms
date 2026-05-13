<?php

namespace App\Observers;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\PushNotificationService;
use Filament\Notifications\Notification;

class AttendanceSessionObserver
{
    /**
     * Handle the AttendanceSession "updated" event.
     */
    public function updated(AttendanceSession $session): void
    {
        if ($session->wasChanged('status') && $session->status === 'Locked') {
            $classNames = $session->classes->pluck('name')->join(', ') ?: 'N/A';
            $this->notifyRelevantUsers(
                'Attendance Session Locked',
                "Attendance for {$classNames} on {$session->session_date->toDateString()} has been locked.",
                route('filament.admin.resources.attendance-sessions.edit', $session)
            );
        }

        if ($session->wasChanged('status') && $session->status === 'Open') {
            $classNames = $session->classes->pluck('name')->join(', ') ?: 'N/A';
            $this->notifyRelevantUsers(
                'Attendance Session Opened',
                "Attendance for {$classNames} on {$session->session_date->toDateString()} is now open.",
                route('filament.admin.resources.attendance-sessions.edit', $session)
            );
        }
    }

    /**
     * Notify users with relevant roles about session events.
     */
    protected function notifyRelevantUsers(string $title, string $body, ?string $url = null): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', ['education_head', 'education_monitor', 'admin', 'superadmin']);
            })
            ->lazy();

        foreach ($users as $user) {
            $notification = Notification::make()
                ->title($title)
                ->body($body);

            if ($url) {
                $notification->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('View Session')
                        ->url($url),
                ]);
            }

            $notification->sendToDatabase($user);
        }

        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUsers(
                User::query()
                    ->where('is_active', true)
                    ->whereHas('roles', function ($query): void {
                        $query->whereIn('name', ['education_head', 'education_monitor', 'admin', 'superadmin']);
                    })
                    ->pluck('id')
                    ->toArray(),
                $title,
                $body,
                ['type' => 'attendance_session', 'url' => $url]
            );
        } catch (\Throwable) {
            // Silently fail push notifications
        }
    }
}
