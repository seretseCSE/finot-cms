<?php

namespace App\Exports;

use App\Models\Rehearsal;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RehearsalExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = Rehearsal::with('createdBy')->get();

        ExportAuditService::log(
            resourceType: 'rehearsals',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/rehearsals.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Date & Time',
            'Location',
            'Status',
            'Recurrence',
            'Created By',
            'Created At',
        ];
    }

    public function map($rehearsal): array
    {
        return [
            $rehearsal->date_time?->format('M d, Y H:i'),
            $rehearsal->location,
            $rehearsal->status,
            $rehearsal->recurrence_type,
            $rehearsal->createdBy?->name,
            $rehearsal->created_at?->format('M d, Y H:i'),
        ];
    }
}
