<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Filament\Resources\DonationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDonation extends EditRecord
{
    protected static string $resource = DonationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => DonationResource::canDelete($this->record)),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Update Donation')
                ->submit(null),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit Donation';
    }

    public function getSubheading(): string
    {
        return 'Update donation details from ' . $this->record->formatted_donor_name;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure recorded_by remains the original user
        unset($data['recorded_by']);

        // Handle Anonymous donor
        if (empty($data['donor_name'])) {
            $data['donor_name'] = null;
        }

        return $data;
    }
}

