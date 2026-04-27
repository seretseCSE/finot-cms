<?php

namespace App\Exports;

use App\Models\Tour;

class TourExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'place' => 'Destination',
            'tour_date' => 'Tour Date',
            'start_time' => 'Start Time',
            'cost_per_person' => 'Cost Per Person',
            'registration_deadline' => 'Registration Deadline',
            'max_capacity' => 'Max Capacity',
            'status' => 'Status',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return Tour::class;
    }

    public static function resourceType(): string
    {
        return 'tours';
    }

    public static function relationships(): array
    {
        return ['createdBy'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'place' => $record->place,
            'tour_date' => $record->tour_date?->format('M d, Y'),
            'start_time' => $record->start_time?->format('H:i'),
            'cost_per_person' => $record->cost_per_person,
            'registration_deadline' => $record->registration_deadline?->format('M d, Y'),
            'max_capacity' => $record->max_capacity,
            'status' => $record->status,
            'created_by' => $record->createdBy?->name,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
