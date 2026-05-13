<?php

namespace App\Filament\Actions;

use Filament\Notifications\Notification;
use App\Models\Contribution;

class ContributionNotificationAction
{
    /**
     * Send notification after contribution is recorded.
     *
     * @param Contribution $contribution The contribution record
     * @return void
     */
    public static function sendCreatedNotification(Contribution $contribution): void
    {
        Notification::make()
            ->title('Contribution Recorded')
            ->body("Successfully recorded contribution of {$contribution->amount} from {$contribution->member->first_name}")
            ->success()
            ->send();
    }

    /**
     * Send notification after contribution is updated.
     *
     * @return void
     */
    public static function sendUpdatedNotification(): void
    {
        Notification::make()
            ->title('Contribution Updated')
            ->body('Contribution information has been updated successfully')
            ->success()
            ->send();
    }
}
