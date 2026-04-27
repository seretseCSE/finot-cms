<?php

namespace App\Filament\Resources\TemporaryFilters\Pages;

use App\Filament\Resources\TemporaryFilters\TemporaryFilterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemporaryFilter extends EditRecord
{
    protected static string $resource = TemporaryFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
