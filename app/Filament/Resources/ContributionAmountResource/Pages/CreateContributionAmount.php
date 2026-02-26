<?php

namespace App\Filament\Resources\ContributionAmountResource\Pages;

use App\Filament\Resources\ContributionAmountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContributionAmount extends CreateRecord
{
    protected static string $resource = ContributionAmountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Save Contribution Amount')
                ->submit(null),
        ];
    }

    public function getHeading(): string
    {
        return 'Create Contribution Amount';
    }

    public function getSubheading(): string
    {
        return 'Define contribution amounts for member groups by month';
    }
}

