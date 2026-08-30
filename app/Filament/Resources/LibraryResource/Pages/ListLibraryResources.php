<?php

namespace App\Filament\Resources\LibraryResource\Pages;

use App\Filament\Resources\LibraryResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListLibraryResources extends ListRecords
{
    protected static string $resource = LibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => LibraryResource::canCreate()),
        ];
    }
}
