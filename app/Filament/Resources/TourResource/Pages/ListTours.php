<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Exports\TourExport;
use App\Filament\Resources\TourResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new TourExport, 'tours_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
                ->visible(fn () => TourResource::canCreate()),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No tours found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first tour to get started.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-map';
    }
}
