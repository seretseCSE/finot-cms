<?php

namespace App\Filament\Resources\HomeworkAssignmentResource\Pages;

use App\Filament\Resources\HomeworkAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomeworkAssignments extends ListRecords
{
    protected static string $resource = HomeworkAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
