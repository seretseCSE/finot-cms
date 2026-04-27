<?php

namespace App\Filament\Resources\TeacherAssignmentResource\Pages;

use App\Filament\Resources\TeacherAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeacherAssignment extends EditRecord
{
    protected static string $resource = TeacherAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
