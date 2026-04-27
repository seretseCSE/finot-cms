<?php

namespace App\Exports;

use App\Models\StudentEnrollment;

class StudentEnrollmentExport extends BaseExport
{
    public static function availableColumns(): array
    {
        return [
            'student' => 'Student',
            'class' => 'Class',
            'academic_year' => 'Academic Year',
            'enrollment_date' => 'Enrollment Date',
            'status' => 'Status',
            'created_at' => 'Created At',
        ];
    }

    public static function modelClass(): string
    {
        return StudentEnrollment::class;
    }

    public static function resourceType(): string
    {
        return 'student_enrollments';
    }

    public static function relationships(): array
    {
        return ['student', 'class', 'academicYear'];
    }

    protected function resolveColumn($record, string $column): mixed
    {
        return match ($column) {
            'student' => $record->student?->full_name,
            'class' => $record->class?->name,
            'academic_year' => $record->academicYear?->name,
            'enrollment_date' => $record->enrollment_date?->format('M d, Y'),
            'status' => $record->status,
            'created_at' => $record->created_at?->format('M d, Y H:i'),
            default => '',
        };
    }
}
