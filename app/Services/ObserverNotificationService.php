<?php

namespace App\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class ObserverNotificationService
{
    /**
     * Send notification to users with specific roles
     */
    public static function notifyUsersByRoles(array $roles, string $title, string $body, ?string $url = null, ?string $notificationType = null): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($roles): void {
                $query->whereIn('name', $roles);
            })
            ->lazy();

        self::sendNotifications($users, $title, $body, $url, $notificationType);
    }

    /**
     * Send notification to specific users
     */
    public static function notifyUsers(Collection $users, string $title, string $body, ?string $url = null, ?string $notificationType = null): void
    {
        self::sendNotifications($users, $title, $body, $url, $notificationType);
    }

    /**
     * Send notification to a single user
     */
    public static function notifyUser(User $user, string $title, string $body, ?string $url = null, ?string $notificationType = null): void
    {
        self::sendNotifications(collect([$user]), $title, $body, $url, $notificationType);
    }

    /**
     * Core notification sending logic
     */
    private static function sendNotifications(Collection $users, string $title, string $body, ?string $url = null, ?string $notificationType = null): void
    {
        foreach ($users as $user) {
            $notification = Notification::make()
                ->title($title)
                ->body($body);

            if ($url) {
                $actionLabel = self::getActionLabel($notificationType);
                $notification->actions([
                    \Filament\Actions\Action::make('view')
                        ->label($actionLabel)
                        ->url($url),
                ]);
            }

            $notification->sendToDatabase($user);
        }

        // Send push notifications
        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUsers(
                $users->pluck('id')->toArray(),
                $title,
                $body,
                array_filter([
                    'type' => $notificationType,
                    'url' => $url
                ])
            );
        } catch (\Throwable $e) {
            Log::warning('Push notification failed', [
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'error' => $e->getMessage(),
                'context' => 'push_notification',
                'user_id' => auth()->id(),
            ]);
            
            // Only show notification to users if they're in Filament context
            if (request()->routeIs('filament.*')) {
                \Filament\Notifications\Notification::make()
                    ->title('Push Notification Failed')
                    ->body('Unable to send push notification: ' . $e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }

    /**
     * Get appropriate action label based on notification type
     */
    private static function getActionLabel(?string $type): string
    {
        return match ($type) {
            'aid_distribution' => 'View Record',
            'contribution' => 'View Contribution',
            'attendance_session' => 'View Session',
            'rehearsal' => 'View Rehearsal',
            default => 'View'
        };
    }

    /**
     * Predefined role groups for common notification scenarios
     */
    public const ROLE_GROUPS = [
        'finance' => ['finance_head', 'nibret_hisab_head', 'admin', 'superadmin'],
        'charity' => ['charity_head', 'admin', 'superadmin'],
        'education' => ['education_head', 'education_monitor', 'admin', 'superadmin'],
        'worship' => ['worship_monitor', 'mezmur_head', 'admin', 'superadmin'],
    ];
}
