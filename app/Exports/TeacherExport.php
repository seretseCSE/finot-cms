<?php

namespace App\Exports;

use App\Models\Teacher;

class TeacherExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'full_name' => 'Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'specialization' => 'Specialization',
            'is_active' => 'Active',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Teacher::class;
    }

    public static function resourceType(): string
    {
        return 'teachers';
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
            'email' => $record->email,
            'specialization' => $record->specialization,
            'is_active' => $record->is_active ? 'Yes' : 'No',
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
