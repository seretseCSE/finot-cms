<?php

namespace App\Exports;

use App\Models\AuditLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class AuditLogsExport implements FromQuery, WithMapping, WithHeadings, WithEvents
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
            $auditLog->causer ? $auditLog->causer->name : 'System',
            $auditLog->causer ? $auditLog->causer->email : 'system@example.com',
            ucfirst($auditLog->action),
            $auditLog->subject_type ? class_basename($auditLog->subject_type) : 'N/A',
            $auditLog->subject_id ?? 'N/A',
            $auditLog->properties ? json_encode($auditLog->properties) : '{}',
            $auditLog->ip_address,
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
            'Subject Type',
            'Subject ID',
            'Properties',
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
                foreach (range('A', 'I') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
                
                // Make header row bold
                $sheet->getStyle('A1:I1')->getFont()->setBold(true);
                $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Add title row
                $sheet->insertBefore(1, 1);
                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', 'Audit Logs Export - Generated on ' . now()->format('Y-m-d H:i:s'));
                $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Add empty row for spacing
                $sheet->insertAfter(1, 1);
                
                // Set row height for title
                $sheet->getRowDimension(1)->setRowHeight(25);
                
                // Apply borders to data
                $sheet->getStyle('A3:I' . ($sheet->getHighestRow()))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // Freeze header row
                $sheet->freezePane('A4');
            },
        ];
    }
}
