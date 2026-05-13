<?php

namespace App\Observers;

use App\Models\Rehearsal;
use App\Models\User;
use App\Services\PushNotificationService;
use Filament\Notifications\Notification;

class RehearsalObserver
{
    /**
     * Handle the Rehearsal "created" event.
     */
    public function created(Rehearsal $rehearsal): void
    {
        $this->notifyRelevantUsers(
            'New Rehearsal Scheduled',
            "A rehearsal has been scheduled for {$rehearsal->date_time->format('M d, Y H:i')} at {$rehearsal->location}.",
            route('filament.admin.resources.rehearsals.edit', $rehearsal)
        );
    }

    /**
     * Handle the Rehearsal "updated" event.
     */
    public function updated(Rehearsal $rehearsal): void
    {
        if ($rehearsal->wasChanged('status')) {
            if ($rehearsal->status === 'Completed') {
                $this->notifyRelevantUsers(
                    'Rehearsal Completed',
                    "The rehearsal on {$rehearsal->date_time->format('M d, Y H:i')} has been marked as completed.",
                    route('filament.admin.resources.rehearsals.edit', $rehearsal)
                );
            } elseif ($rehearsal->status === 'Cancelled') {
                $this->notifyRelevantUsers(
                    'Rehearsal Cancelled',
                    "The rehearsal on {$rehearsal->date_time->format('M d, Y H:i')} has been cancelled.",
                    route('filament.admin.resources.rehearsals.edit', $rehearsal)
                );
            }
        }
    }

    /**
     * Notify users with relevant roles about rehearsal events.
     */
    protected function notifyRelevantUsers(string $title, string $body, ?string $url = null): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', ['worship_monitor', 'mezmur_head', 'admin', 'superadmin']);
            })
            ->lazy();

        foreach ($users as $user) {
            $notification = Notification::make()
                ->title($title)
                ->body($body);

            if ($url) {
                $notification->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('View Rehearsal')
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
                        $query->whereIn('name', ['worship_monitor', 'mezmur_head', 'admin', 'superadmin']);
                    })
                    ->pluck('id')
                    ->toArray(),
                $title,
                $body,
                ['type' => 'rehearsal', 'url' => $url]
            );
        } catch (\Throwable) {
            // Silently fail push notifications
        }
    }
}
