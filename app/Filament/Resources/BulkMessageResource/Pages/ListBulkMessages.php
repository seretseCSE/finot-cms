<?php

namespace App\Filament\Resources\BulkMessageResource\Pages;

use App\Filament\Resources\BulkMessageResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListBulkMessages extends ListRecords
{
    protected static string $resource = BulkMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
