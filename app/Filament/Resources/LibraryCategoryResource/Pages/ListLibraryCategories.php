<?php

namespace App\Filament\Resources\LibraryCategoryResource\Pages;

use App\Filament\Resources\LibraryCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLibraryCategories extends ListRecords
{
    protected static string $resource = LibraryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => LibraryCategoryResource::canCreate()),
        ];
    }
}
