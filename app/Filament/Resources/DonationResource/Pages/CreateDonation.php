<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Filament\Resources\DonationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDonation extends CreateRecord
{
    protected static string $resource = DonationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Record Donation')
                ->submit(null),
        ];
    }

    public function getHeading(): string
    {
        return 'Record Donation';
    }

    public function getSubheading(): string
    {
        return 'Record charitable donations to the church';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        // Save custom option usage and pending options
        \App\Filament\Forms\Components\CustomOptionSelect::saveUsageAndPending($data, ['donation_type']);

        return $record;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Debug: Log the incoming data
        \Log::info('CreateDonation mutateFormDataBeforeCreate data:', $data);

        // Set recorded_by to current user
        $data['recorded_by'] = auth()->id();

        // Handle Anonymous donor
        if (empty($data['donor_name'])) {
            $data['donor_name'] = null;
        }

        // Handle CustomOptionSelect data
        if (isset($data['donation_type']) && $data['donation_type'] === '__other__') {
            $data['custom_donation_type'] = $data['donation_type_other'] ?? null;
            $data['donation_type'] = 'Other';
        }

        \Log::info('CreateDonation mutateFormDataBeforeCreate processed data:', $data);

        return $data;
    }
}
