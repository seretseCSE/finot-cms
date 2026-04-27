<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AuditLogsExport implements FromQuery, WithEvents, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function map($auditLog): array
    {
        return [
            $auditLog->created_at->format('Y-m-d H:i:s'),
            $auditLog->user ? $auditLog->user->name : 'System',
            $auditLog->user ? $auditLog->user->email : 'system@example.com',
            ucfirst($auditLog->action_type ?? 'N/A'),
            $auditLog->entity_type ? class_basename($auditLog->entity_type) : 'N/A',
            $auditLog->entity_id ?? 'N/A',
            $auditLog->old_value ? json_encode($auditLog->old_value) : '{}',
            $auditLog->new_value ? json_encode($auditLog->new_value) : '{}',
            $auditLog->ip_address ?? 'N/A',
            $auditLog->user_agent ?? 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'Date & Time',
            'User Name',
            'User Email',
            'Action',
            'Entity Type',
            'Entity ID',
            'Old Value',
            'New Value',
            'IP Address',
            'User Agent',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Auto-size columns
                foreach (range('A', 'J') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Make header row bold
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Add title row
                $sheet->insertBefore(1, 1);
                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1', 'Audit Logs Export - Generated on '.now()->format('Y-m-d H:i:s'));
                $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Add empty row for spacing
                $sheet->insertAfter(1, 1);

                // Set row height for title
                $sheet->getRowDimension(1)->setRowHeight(25);

                // Apply borders to data
                $sheet->getStyle('A3:J'.($sheet->getHighestRow()))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // Freeze header row
                $sheet->freezePane('A4');
            },
        ];
    }
}
