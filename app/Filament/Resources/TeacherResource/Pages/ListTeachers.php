<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Exports\TeacherExport;
use App\Filament\Resources\TeacherResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new TeacherExport, 'teachers_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make(),
        ];
    }
}
