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
        return 'Record an individual member contribution';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['recorded_by'] = auth()->id();
        $data['is_paid'] = $data['status'] === 'Paid';

        return parent::handleRecordCreation($data);
    }
}
