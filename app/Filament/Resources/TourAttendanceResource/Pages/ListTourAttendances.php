<?php

namespace App\Filament\Resources\TourAttendanceResource\Pages;

use App\Filament\Resources\TourAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTourAttendances extends ListRecords
{
    protected static string $resource = TourAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
