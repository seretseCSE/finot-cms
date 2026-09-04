<?php

namespace App\Filament\Resources\ClassMaterialResource\Pages;

use App\Filament\Resources\ClassMaterialResource;
use App\Services\Learning\ClassContentNotifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClassMaterial extends CreateRecord
{
    protected static string $resource = ClassMaterialResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        if (! empty($data['is_published'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->is_published) {
            app(ClassContentNotifier::class)->materialPublished($this->record);
        }
    }
}
