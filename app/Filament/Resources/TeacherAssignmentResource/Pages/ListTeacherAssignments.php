<?php

namespace App\Filament\Resources\TeacherAssignmentResource\Pages;

use App\Filament\Resources\TeacherAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeacherAssignments extends ListRecords
{
    protected static string $resource = TeacherAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
