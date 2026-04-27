<?php

namespace App\Filament\Resources\LibraryCategoryResource\Pages;

use App\Filament\Resources\LibraryCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLibraryCategory extends CreateRecord
{
    protected static string $resource = LibraryCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
