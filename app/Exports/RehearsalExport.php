<?php

namespace App\Exports;

use App\Models\Rehearsal;

class RehearsalExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'date_time' => 'Date & Time',
            'location' => 'Location',
            'status' => 'Status',
            'recurrence_type' => 'Recurrence',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Rehearsal::class;
    }

    public static function resourceType(): string
    {
        return 'rehearsals';
    }

    public static function relationships(): array
    {
        return ['createdBy'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'date_time' => $record->date_time?->format('M d, Y H:i'),
            'location' => $record->location,
            'status' => $record->status,
            'recurrence_type' => $record->recurrence_type,
            'created_by' => $record->createdBy?->name,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
