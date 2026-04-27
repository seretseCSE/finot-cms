<?php

namespace App\Filament\Resources\RehearsalResource\Pages;

use App\Filament\Resources\RehearsalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRehearsals extends ListRecords
{
    protected static string $resource = RehearsalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(\App\Filament\Exports\RehearsalExporter::class)
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            Actions\CreateAction::make()
                ->visible(fn () => RehearsalResource::canCreate()),
        ];
    }
}
