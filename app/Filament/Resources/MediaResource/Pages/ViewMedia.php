<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMedia extends ViewRecord
{
    protected static string $resource = MediaResource::class;

    protected string $view = 'filament.resources.media-resource.pages.view-media';

    protected function getViewData(): array
    {
        $record = $this->getRecord();

        // Find all media items that share the same album / title
        $groupKey = $record->event_album ?: $record->title;

        $relatedMedia = \App\Models\MediaItem::query()
            ->when(
                $record->event_album,
                fn ($q) => $q->where('event_album', $record->event_album),
                fn ($q) => $q->where('title', $record->title)
            )
            ->where('id', '!=', $record->id)
            ->orderBy('created_at')
            ->get();

        return [
            'relatedMedia' => $relatedMedia,
            'groupKey' => $groupKey,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
