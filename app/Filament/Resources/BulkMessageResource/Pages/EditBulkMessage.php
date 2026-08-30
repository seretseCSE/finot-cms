<?php

namespace App\Filament\Resources\BulkMessageResource\Pages;

use App\Filament\Resources\BulkMessageResource;
use Filament\Resources\Pages\EditRecord;

class EditBulkMessage extends EditRecord
{
    protected static string $resource = BulkMessageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $audience = $data['audience'] ?? [];
        $data['audience'] = [
            'class_ids' => $audience['class_ids'] ?? [],
            'group_ids' => $audience['group_ids'] ?? [],
            'member_ids' => $audience['member_ids'] ?? [],
            'member_types' => $audience['member_types'] ?? [],
            'search' => $audience['search'] ?? '',
        ];
        $data['confirm_global'] = (bool) ($audience['global'] ?? $data['confirm_global'] ?? false);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['audience'] = [
            'global' => (bool) ($data['confirm_global'] ?? false),
            'department_id' => $data['department_id'] ?? $this->record->department_id,
            'class_ids' => $this->data['audience']['class_ids'] ?? [],
            'group_ids' => $this->data['audience']['group_ids'] ?? [],
            'member_ids' => $this->data['audience']['member_ids'] ?? [],
            'member_types' => $this->data['audience']['member_types'] ?? [],
            'search' => $this->data['audience']['search'] ?? '',
        ];

        return $data;
    }
}
