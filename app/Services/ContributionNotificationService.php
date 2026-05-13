<?php

namespace App\Services;

use Filament\Notifications\Notification;

class ContributionNotificationService
{
    /**
     * Send notification after contribution creation
     */
    public function sendCreateNotification($record): void
    {
        Notification::make()
            ->title('Contribution Recorded')
            ->body("Successfully recorded contribution of {$record->formatted_amount} from {$record->formatted_member_name}")
            ->success()
            ->send();
    }
}
