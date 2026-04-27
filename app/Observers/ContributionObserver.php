<?php

namespace App\Observers;

use App\Models\Contribution;
use App\Models\User;
use App\Services\PushNotificationService;
use Filament\Notifications\Notification;

class ContributionObserver
{
    /**
     * Handle the Contribution "created" event.
     */
    public function created(Contribution $contribution): void
    {
        $memberName = $contribution->member?->full_name ?? 'A member';

        $this->notifyRelevantUsers(
            'Contribution Recorded',
            "{$memberName} contributed ETB {$contribution->amount} for {$contribution->month_name}.",
            route('filament.admin.resources.contributions.edit', $contribution)
        );
    }

    /**
     * Handle the Contribution "updated" event.
     */
    public function updated(Contribution $contribution): void
    {
        if ($contribution->wasChanged('status')) {
            $this->notifyRelevantUsers(
                'Contribution Status Changed',
                "Contribution status changed to {$contribution->status}.",
                route('filament.admin.resources.contributions.edit', $contribution)
            );
        }
    }

    /**
     * Notify users with relevant roles about contribution events.
     */
    protected function notifyRelevantUsers(string $title, string $body, ?string $url = null): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', ['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
            })
            ->get();

        foreach ($users as $user) {
            $notification = Notification::make()
                ->title($title)
                ->body($body);

            if ($url) {
                $notification->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('View Contribution')
                        ->url($url),
                ]);
            }

            $notification->sendToDatabase($user);
        }

        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUsers(
                $users->pluck('id')->toArray(),
                $title,
                $body,
                ['type' => 'contribution', 'url' => $url]
            );
        } catch (\Throwable) {
            // Silently fail push notifications
        }
    }
}
