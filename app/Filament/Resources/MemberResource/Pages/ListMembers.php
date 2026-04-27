<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Exports\MemberExporter;
use App\Filament\Resources\MemberResource;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ExportAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(MemberExporter::class)
                ->formats([
                    ExportFormat::Xlsx,
                    ExportFormat::Csv,
                ])
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            CreateAction::make()
                ->label('New Member')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
