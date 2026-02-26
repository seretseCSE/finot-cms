<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Filament\Resources\DonationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => DonationResource::canCreate()),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No donations recorded';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Start recording donations to track charitable contributions to the church.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-gift';
    }
}

