<?php

namespace App\Exports;

use App\Models\Tour;
use App\Services\ExportAuditService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TourExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        $records = Tour::with('createdBy')->get();

        ExportAuditService::log(
            resourceType: 'tours',
            format: 'xlsx',
            recordCount: $records->count(),
            filePath: 'exports/tours.xlsx',
        );

        return $records;
    }

    public function headings(): array
    {
        return [
            'Destination',
            'Tour Date',
            'Start Time',
            'Cost Per Person',
            'Registration Deadline',
            'Max Capacity',
            'Status',
            'Created By',
            'Created At',
        ];
    }

    public function map($tour): array
    {
        return [
            $tour->place,
            $tour->tour_date?->format('M d, Y'),
            $tour->start_time?->format('H:i'),
            $tour->cost_per_person,
            $tour->registration_deadline?->format('M d, Y'),
            $tour->max_capacity,
            $tour->status,
            $tour->createdBy?->name,
            $tour->created_at?->format('M d, Y H:i'),
        ];
    }
}
