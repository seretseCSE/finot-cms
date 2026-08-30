<?php

namespace App\Filament\Resources\RehearsalAttendanceResource\Pages;

use App\Filament\Resources\RehearsalAttendanceResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListRehearsalAttendances extends ListRecords
{
    protected static string $resource = RehearsalAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
