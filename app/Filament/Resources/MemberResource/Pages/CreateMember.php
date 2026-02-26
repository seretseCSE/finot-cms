<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate member code if not provided
        if (empty($data['member_code'])) {
            $data['member_code'] = \App\Models\Member::generateMemberCode();
        }

        return $data;
    }
}

