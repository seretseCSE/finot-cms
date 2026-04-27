<?php

namespace App\Exports;

use App\Models\Member;

class MemberExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'member_code' => 'Member ID',
            'first_name' => 'First Name',
            'father_name' => 'Father Name',
            'grandfather_name' => 'Grandfather Name',
            'member_type' => 'Member Type',
            'status' => 'Status',
            'phone' => 'Phone',
            'email' => 'Email',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Member::class;
    }

    public static function resourceType(): string
    {
        return 'members';
    }

    public static function relationships(): array
    {
        return [];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'member_code' => $record->member_code,
            'first_name' => $record->first_name,
            'father_name' => $record->father_name,
            'grandfather_name' => $record->grandfather_name,
            'member_type' => $record->member_type,
            'status' => $record->status,
            'phone' => $record->phone,
            'email' => $record->email,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
