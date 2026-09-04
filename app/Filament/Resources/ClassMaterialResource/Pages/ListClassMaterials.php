<?php

namespace App\Filament\Resources\ClassMaterialResource\Pages;

use App\Filament\Resources\ClassMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClassMaterials extends ListRecords
{
    protected static string $resource = ClassMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
