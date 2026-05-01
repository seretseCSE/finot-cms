<?php

namespace App\Exports;

use App\Models\ParentModel;

class ParentExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'full_name' => 'Full Name',
            'phone' => 'Phone',
            'member_count' => 'Linked Children',
            'is_active' => 'Status',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return ParentModel::class;
    }

    public static function resourceType(): string
    {
        return 'parents';
    }

    public static function relationships(): array
    {
        return [];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'full_name' => $record->full_name,
            'phone' => $record->phone,
            'member_count' => $record->member_count,
            'is_active' => $record->is_active ? 'Active' : 'Inactive',
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
