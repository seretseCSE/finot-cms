<?php

namespace App\Filament\Resources\CourseLessonResource\Pages;

use App\Filament\Resources\CourseLessonResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListCourseLessons extends ListRecords
{
    protected static string $resource = CourseLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => CourseLessonResource::canCreate()),
        ];
    }
}
