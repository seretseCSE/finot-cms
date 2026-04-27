<?php

namespace App\Filament\Resources\TemporaryFilters\Pages;

use App\Filament\Resources\TemporaryFilters\TemporaryFilterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemporaryFilters extends ListRecords
{
    protected static string $resource = TemporaryFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
