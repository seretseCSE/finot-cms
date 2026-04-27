<?php

namespace App\Exports;

use App\Models\StudentEnrollment;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentEnrollmentExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = StudentEnrollment::with(['student', 'class', 'academicYear'])->get();

        ExportAuditService::log(
            resourceType: 'student_enrollments',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/student_enrollments.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Student',
            'Class',
            'Academic Year',
            'Enrollment Date',
            'Status',
            'Created At',
        ];
    }

    public function map($enrollment): array
    {
        return [
            $enrollment->student?->full_name,
            $enrollment->class?->name,
            $enrollment->academicYear?->name,
            $enrollment->enrollment_date?->format('M d, Y'),
            $enrollment->status,
            $enrollment->created_at?->format('M d, Y H:i'),
        ];
    }
}
