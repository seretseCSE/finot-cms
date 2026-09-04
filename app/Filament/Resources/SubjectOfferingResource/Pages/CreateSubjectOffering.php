<?php

namespace App\Filament\Resources\SubjectOfferingResource\Pages;

use App\Filament\Resources\SubjectOfferingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSubjectOffering extends CreateRecord
{
    protected static string $resource = SubjectOfferingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
