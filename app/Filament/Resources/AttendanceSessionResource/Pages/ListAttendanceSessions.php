<?php

namespace App\Filament\Resources\AttendanceSessionResource\Pages;

use App\Exports\AttendanceSessionExport;
use App\Filament\Resources\AttendanceSessionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new AttendanceSessionExport, 'attendance_sessions_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
                ->visible(fn () => AttendanceSessionResource::canCreate()),
        ];
    }
}
