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
        return 'Record charitable donations to the church (separate from member contributions)';
    }

    protected function handleRecordCreation(array $data): Model
    {
        return parent::handleRecordCreation($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set recorded_by to current user
        $data['recorded_by'] = auth()->id();

        // Handle Anonymous donor
        if (empty($data['donor_name'])) {
            $data['donor_name'] = null;
        }

        return $data;
    }
}

