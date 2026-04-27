<?php

namespace App\Filament\Resources\ContributionResource\Pages;

use App\Exports\ContributionExport;
use App\Filament\Resources\ContributionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListContributions extends ListRecords
{
    protected static string $resource = ContributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new ContributionExport, 'contributions_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
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
