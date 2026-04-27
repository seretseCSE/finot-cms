<?php

namespace App\Filament\Resources\LossRecordResource\Pages;

use App\Filament\Resources\LossRecordResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLossRecord extends CreateRecord
{
    protected static string $resource = LossRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = Auth::id();

        return $data;
    }
}
