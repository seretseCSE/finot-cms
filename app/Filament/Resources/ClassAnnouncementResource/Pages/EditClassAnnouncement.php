<?php

namespace App\Filament\Resources\ClassAnnouncementResource\Pages;

use App\Filament\Resources\ClassAnnouncementResource;
use App\Services\Learning\ClassContentNotifier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClassAnnouncement extends EditRecord
{
    protected static string $resource = ClassAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $wasPublished = (bool) $this->record->is_published;
        if (! empty($data['is_published']) && ! $wasPublished) {
            $data['published_at'] = now();
            $this->shouldNotify = true;
        }

        return $data;
    }

    protected bool $shouldNotify = false;

    protected function afterSave(): void
    {
        if ($this->shouldNotify || ($this->record->wasChanged('is_published') && $this->record->is_published)) {
            app(ClassContentNotifier::class)->announcePublished($this->record);
        }
    }
}
