<?php

namespace App\Filament\Resources\ClassAnnouncementResource\Pages;

use App\Filament\Resources\ClassAnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClassAnnouncements extends ListRecords
{
    protected static string $resource = ClassAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
