<?php

namespace App\Exports;

use App\Services\Members\StudentExcelImporter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new StudentImportTemplateDataSheet(),
            new StudentImportTemplateGuideSheet(),
        ];
    }
}

class StudentImportTemplateDataSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Students';
    }

    public function headings(): array
    {
        return array_values(StudentExcelImporter::columns());
    }

    public function array(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E79'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->freezePane('A2');
            },
        ];
    }
}

class StudentImportTemplateGuideSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Instructions';
    }

    public function headings(): array
    {
        return ['Column', 'Required', 'Accepted values'];
    }

    public function array(): array
    {
        $rows = [];

        foreach (StudentExcelImporter::columnGuide() as $column => $meta) {
            $rows[] = [
                $column,
                $meta['required'] ? 'Yes' : 'No',
                $meta['help'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Example', '', implode(' | ', StudentExcelImporter::exampleRow())];
        $rows[] = ['Notes', '', 'Keep the header row on the Students sheet. Phone numbers should be 9 digits (e.g. 911234567). Optional Group can also be chosen in the import dialog.'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E79'],
                ],
            ],
        ];
    }
}
