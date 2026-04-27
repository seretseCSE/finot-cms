<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record): bool => $record && TourResource::canDelete($record))
                ->before(function ($record) {
                    if (! $record->canBeDeleted()) {
                        throw new \Exception('Cannot delete tour with passengers. Use Cancel action instead.');
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Set created_by if not set
        if (! isset($data['created_by'])) {
            $data['created_by'] = auth()->id();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Auto-update status if tour date has passed or is full
        $this->getRecord()->refresh();
        $this->getRecord()->updateStatusIfNeeded();

        // Log status changes to audit trail
        $original = $this->getRecord()->getOriginal();
        $current = $this->getRecord()->toArray();

        if (isset($original['status']) && isset($current['status']) && $original['status'] !== $current['status']) {
            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                'tier' => 2,
                'action' => 'tour_status_changed',
                'entity_id' => $this->getRecord()->id,
                'entity_type' => 'tour',
                'old_value' => json_encode(['status' => $original['status']]),
                'new_value' => json_encode(['status' => $current['status']]),
                'user_id' => auth()->id(),
                'timestamp' => now()->toDateTimeString(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
