<?php

namespace App\Filament\Resources\RehearsalAttendanceResource\Pages;

use App\Filament\Resources\RehearsalAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRehearsalAttendance extends EditRecord
{
    protected static string $resource = RehearsalAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
