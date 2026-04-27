<?php

namespace App\Filament\Resources\AidDistributionResource\Pages;

use App\Filament\Resources\AidDistributionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAidDistributions extends ListRecords
{
    protected static string $resource = AidDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(\App\Filament\Exports\AidDistributionExporter::class)
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            Actions\CreateAction::make(),
        ];
    }
}
