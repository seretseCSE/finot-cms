<?php

namespace App\Filament\Resources\DuplicateRecords\Pages;

use App\Filament\Resources\DuplicateRecords\DuplicateRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDuplicateRecord extends EditRecord
{
    protected static string $resource = DuplicateRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
