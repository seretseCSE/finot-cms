<?php

namespace App\Filament\Resources\HomeworkAssignmentResource\Pages;

use App\Filament\Resources\HomeworkAssignmentResource;
use App\Services\Learning\ClassContentNotifier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeworkAssignment extends EditRecord
{
    protected static string $resource = HomeworkAssignmentResource::class;

    protected bool $shouldNotify = false;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['is_published']) && ! $this->record->is_published) {
            $data['published_at'] = now();
            $this->shouldNotify = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->shouldNotify || ($this->record->wasChanged('is_published') && $this->record->is_published)) {
            app(ClassContentNotifier::class)->homeworkPublished($this->record);
        }
    }
}
