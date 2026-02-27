<?php

namespace App\Filament\Resources\SchoolClassResource\Pages;

use App\Filament\Resources\SchoolClassResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSchoolClass extends CreateRecord
{
    protected static string $resource = SchoolClassResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;
        return $data;
    }
}

