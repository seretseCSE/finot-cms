<?php

namespace App\Filament\Resources\ContributionResource\Pages;

use App\Filament\Resources\ContributionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContribution extends CreateRecord
{
    protected static string $resource = ContributionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Record Contribution')
                ->submit(null),
        ];
    }

    public function getHeading(): string
    {
        return 'Record Contribution';
    }

    public function getSubheading(): string
    {
        return 'Record member contributions for the current academic year';
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Handle multi-month recording in the resource's afterCreate method
        return parent::handleRecordCreation($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set recorded_by to current user
        $data['recorded_by'] = auth()->id();

        return $data;
    }
}

