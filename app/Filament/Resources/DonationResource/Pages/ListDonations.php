<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Exports\DonationExport;
use App\Filament\Resources\DonationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new DonationExport, 'donations_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
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
