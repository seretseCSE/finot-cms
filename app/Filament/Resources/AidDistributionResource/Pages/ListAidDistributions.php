<?php

namespace App\Filament\Resources\AidDistributionResource\Pages;

use App\Exports\AidDistributionExport;
use App\Filament\Resources\AidDistributionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListAidDistributions extends ListRecords
{
    protected static string $resource = AidDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new AidDistributionExport, 'aid_distributions_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make(),
        ];
    }
}
