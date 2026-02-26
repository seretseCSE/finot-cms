<?php

namespace App\Filament\Resources\ContributionAmountResource\Pages;

use App\Filament\Resources\ContributionAmountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContributionAmounts extends ListRecords
{
    protected static string $resource = ContributionAmountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => ContributionAmountResource::canCreate()),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
}

