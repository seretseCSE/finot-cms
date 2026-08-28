<?php

namespace App\Filament\Resources\BulkMessageResource\Pages;

use App\Enums\BulkMessageStatus;
use App\Filament\Resources\BulkMessageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBulkMessage extends CreateRecord
{
    protected static string $resource = BulkMessageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sender_id'] = Auth::id();
        $data['status'] = BulkMessageStatus::Draft->value;
        $data['channels'] = ['in_app'];
        $data['audience'] = [
            'global' => (bool) ($data['confirm_global'] ?? false),
            'department_id' => $data['department_id'] ?? Auth::user()?->department_id,
            'class_ids' => $this->data['audience']['class_ids'] ?? [],
            'group_ids' => $this->data['audience']['group_ids'] ?? [],
            'member_ids' => $this->data['audience']['member_ids'] ?? [],
        ];

        return $data;
    }
}
