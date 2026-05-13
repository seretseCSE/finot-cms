<?php

namespace App\Filament\Actions;

use Filament\Notifications\Notification;
use App\Models\Donation;

class DonationNotificationAction
{
    /**
     * Send notification after donation is recorded.
     *
     * @param Donation $donation The donation record
     * @return void
     */
    public static function sendCreatedNotification(Donation $donation): void
    {
        Notification::make()
            ->title('Donation Recorded')
            ->body("Successfully recorded donation of {$donation->formatted_amount} from {$donation->formatted_donor_name}")
            ->success()
            ->send();
    }

    /**
     * Send notification after donation is updated.
     *
     * @return void
     */
    public static function sendUpdatedNotification(): void
    {
        Notification::make()
            ->title('Donation Updated')
            ->body('Donation information has been updated successfully')
            ->success()
            ->send();
    }
}
