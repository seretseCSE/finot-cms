<?php

namespace App\Filament\Resources\RehearsalResource\Pages;

use App\Filament\Resources\RehearsalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRehearsal extends CreateRecord
{
    protected static string $resource = RehearsalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
