<?php

namespace App\Exports;

use App\Models\Teacher;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeacherExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = Teacher::all();

        ExportAuditService::log(
            resourceType: 'teachers',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/teachers.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Phone',
            'Email',
            'Specialization',
            'Active',
            'Created At',
        ];
    }

    public function map($teacher): array
    {
        return [
            $teacher->full_name,
            $teacher->phone,
            $teacher->email,
            $teacher->specialization,
            $teacher->is_active ? 'Yes' : 'No',
            $teacher->created_at?->format('M d, Y H:i'),
        ];
    }
}
