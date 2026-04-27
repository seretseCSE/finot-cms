<?php

namespace App\Exports;

use App\Models\AttendanceSession;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceSessionExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = AttendanceSession::with(['class', 'academicYear', 'createdBy'])->get();

        ExportAuditService::log(
            resourceType: 'attendance_sessions',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/attendance_sessions.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Class',
            'Session Date',
            'Academic Year',
            'Status',
            'Locked At',
            'Created By',
            'Created At',
        ];
    }

    public function map($session): array
    {
        return [
            $session->class?->name,
            $session->session_date?->format('M d, Y'),
            $session->academicYear?->name,
            $session->status,
            $session->locked_at?->format('M d, Y H:i'),
            $session->createdBy?->name,
            $session->created_at?->format('M d, Y H:i'),
        ];
    }
}
