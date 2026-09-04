<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AcademicArrayExport implements FromArray, ShouldAutoSize, WithCustomCsvSettings, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headingRow
     * @param  list<list<mixed>>  $dataRows
     */
    public function __construct(
        private array $headingRow,
        private array $dataRows,
        private string $sheetTitle = 'Report',
    ) {
    }

    public function headings(): array
    {
        return $this->headingRow;
    }

    public function array(): array
    {
        return $this->dataRows;
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true,
        ];
    }
}
