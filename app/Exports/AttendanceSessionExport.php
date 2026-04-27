<?php

namespace App\Exports;

use App\Models\AttendanceSession;

class AttendanceSessionExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'class' => 'Class',
            'session_date' => 'Session Date',
            'academic_year' => 'Academic Year',
            'status' => 'Status',
            'locked_at' => 'Locked At',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return AttendanceSession::class;
    }

    public static function resourceType(): string
    {
        return 'attendance_sessions';
    }

    public static function relationships(): array
    {
        return ['class', 'academicYear', 'createdBy'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'class' => $record->class?->name,
            'session_date' => $record->session_date?->format('M d, Y'),
            'academic_year' => $record->academicYear?->name,
            'status' => $record->status,
            'locked_at' => $record->locked_at?->format('M d, Y H:i'),
            'created_by' => $record->createdBy?->name,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
