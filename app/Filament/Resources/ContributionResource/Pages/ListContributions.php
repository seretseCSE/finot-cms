<?php

namespace App\Filament\Resources\ContributionResource\Pages;

use App\Filament\Resources\ContributionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContributions extends ListRecords
{
    protected static string $resource = ContributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(\App\Filament\Exports\ContributionExporter::class)
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            Actions\CreateAction::make()
                ->visible(fn () => ContributionResource::canCreate()),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No contributions recorded';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Start recording individual member contributions.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }
}
