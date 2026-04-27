<?php

namespace App\Filament\Resources\RehearsalResource\Pages;

use App\Exports\RehearsalExport;
use App\Filament\Resources\RehearsalResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListRehearsals extends ListRecords
{
    protected static string $resource = RehearsalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new RehearsalExport, 'rehearsals_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
                ->visible(fn () => RehearsalResource::canCreate()),
        ];
    }
}
