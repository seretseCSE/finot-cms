<?php

namespace App\Observers;

use App\Models\AidDistribution;
use App\Models\User;
use App\Services\PushNotificationService;
use Filament\Notifications\Notification;

class AidDistributionObserver
{
    /**
     * Handle the AidDistribution "created" event.
     */
    public function created(AidDistribution $distribution): void
    {
        $beneficiaryName = $distribution->beneficiary?->name ?? 'a beneficiary';

        $this->notifyRelevantUsers(
            'Aid Distributed',
            "Aid of ETB {$distribution->amount} has been distributed to {$beneficiaryName}.",
            route('filament.admin.resources.aid-distributions.edit', $distribution)
        );
    }

    /**
     * Handle the AidDistribution "updated" event.
     */
    public function updated(AidDistribution $distribution): void
    {
        if ($distribution->wasChanged('is_locked') && $distribution->is_locked) {
            $this->notifyRelevantUsers(
                'Aid Distribution Locked',
                'Aid distribution record has been locked and finalized.',
                route('filament.admin.resources.aid-distributions.edit', $distribution)
            );
        }
    }

    /**
     * Notify users with relevant roles about aid distribution events.
     */
    protected function notifyRelevantUsers(string $title, string $body, ?string $url = null): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', ['charity_head', 'admin', 'superadmin']);
            })
            ->get();

        foreach ($users as $user) {
            $notification = Notification::make()
                ->title($title)
                ->body($body);

            if ($url) {
                $notification->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('View Record')
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
                ['type' => 'aid_distribution', 'url' => $url]
            );
        } catch (\Throwable) {
            // Silently fail push notifications
        }
    }
}
