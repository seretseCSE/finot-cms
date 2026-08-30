<?php

namespace App\Filament\Resources\DuplicateRecords\Pages;

use App\Filament\Resources\DuplicateRecords\DuplicateRecordResource;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Pages\ListRecords;

class ListDuplicateRecords extends ListRecords
{
    protected static string $resource = DuplicateRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
