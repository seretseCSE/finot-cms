<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to current user
        $data['created_by'] = auth()->user()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log to audit trail
        \Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_created',
            'entity_id' => $this->record->id,
            'entity_type' => 'tour',
            'old_value' => null,
            'new_value' => json_encode([
                'place' => $this->record->place,
                'tour_date' => $this->record->tour_date,
                'start_time' => $this->record->start_time,
            ]),
            'user_id' => auth()->user()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

