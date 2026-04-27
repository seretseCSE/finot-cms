<?php

namespace App\Filament\Resources\LibrarySubcategoryResource\Pages;

use App\Filament\Resources\LibrarySubcategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLibrarySubcategory extends CreateRecord
{
    protected static string $resource = LibrarySubcategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
