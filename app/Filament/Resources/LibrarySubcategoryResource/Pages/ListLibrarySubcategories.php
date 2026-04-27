<?php

namespace App\Filament\Resources\LibrarySubcategoryResource\Pages;

use App\Filament\Resources\LibrarySubcategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLibrarySubcategories extends ListRecords
{
    protected static string $resource = LibrarySubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => LibrarySubcategoryResource::canCreate()),
        ];
    }
}
