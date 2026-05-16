<?php

namespace App\Services;

use App\Models\Donation;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DonationDataService
{
    /**
     * Process donation data before saving
     */
    public function processBeforeSave(array $data): array
    {
        \Log::info('Donation beforeSave data:', $data);

        // Only set recorded_by if not already present (preserves original during edits)
        if (! isset($data['recorded_by'])) {
            $data['recorded_by'] = Auth::id();
        }

        if (empty($data['donor_name'])) {
            $data['donor_name'] = null;
        }

        \Log::info('Donation beforeSave processed data:', $data);

        return $data;
    }

    /**
     * Send notification after donation creation
     */
    public function sendCreateNotification($record): void
    {
        Notification::make()
            ->title('Donation Recorded')
            ->body("Successfully recorded donation of {$record->formatted_amount} from {$record->formatted_donor_name}")
            ->success()
            ->send();
    }

    /**
     * Send notification after donation update
     */
    public function sendUpdateNotification(): void
    {
        Notification::make()
            ->title('Donation Updated')
            ->body('Donation information has been updated successfully')
            ->success()
            ->send();
    }
}
