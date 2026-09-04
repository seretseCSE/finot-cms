<?php

namespace App\Filament\Resources\ClassAnnouncementResource\Pages;

use App\Filament\Resources\ClassAnnouncementResource;
use App\Services\Learning\ClassContentNotifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClassAnnouncement extends CreateRecord
{
    protected static string $resource = ClassAnnouncementResource::class;

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
            app(ClassContentNotifier::class)->announcePublished($this->record);
        }
    }
}
