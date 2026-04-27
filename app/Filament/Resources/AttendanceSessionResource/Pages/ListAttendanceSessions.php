<?php

namespace App\Filament\Resources\AttendanceSessionResource\Pages;

use App\Filament\Resources\AttendanceSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(\App\Filament\Exports\AttendanceSessionExporter::class)
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            Actions\CreateAction::make()
                ->visible(fn () => AttendanceSessionResource::canCreate()),
        ];
    }
}
