<?php

namespace App\Exports;

use App\Models\ExportLog;
use App\Models\Donation;
use App\Helpers\EthiopianDateHelper;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DonationExporter implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected $filters = [];
    protected ?string $filePath = null;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Donation::with(['recordedBy']);

        // Apply filters
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('donation_date', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('donation_date', '<=', $this->filters['date_to']);
        }

        if (!empty($this->filters['donation_types'])) {
            $query->whereIn('donation_type', $this->filters['donation_types']);
        }

        $records = $query->orderBy('donation_date', 'desc')->get();

        // Log export
        $this->logExport($records->count());

        return $records;
    }

    public function headings(): array
    {
        return [
            'Donor Name',
            'Amount',
            'Donation Type',
            'Custom Donation Type',
            'Donation Date (Ethiopian)',
            'Donation Date (Gregorian)',
            'Notes',
            'Recorded By',
            'Created At',
        ];
    }

    public function map($donation): array
    {
        return [
            $donation->formatted_donor_name,
            $donation->amount,
            $donation->donation_type,
            $donation->custom_donation_type,
            app(EthiopianDateHelper::class)->toEthiopian($donation->donation_date)['month_name_am'] . ' ' . 
            app(EthiopianDateHelper::class)->toEthiopian($donation->donation_date)['day'] . ', ' .
            app(EthiopianDateHelper::class)->toEthiopian($donation->donation_date)['year'],
            $donation->donation_date->format('M d, Y'),
            $donation->notes,
            $donation->recordedBy->name,
            $donation->created_at->format('M d, Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Freeze header row
        $sheet->freezePane('A2');

        // Style header row
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '16A34A'],
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add totals row at the bottom
        $lastRow = $sheet->getHighestRow();
        $totalsRow = $lastRow + 1;
        
        $sheet->setCellValue('A' . $totalsRow, 'TOTAL:');
        $sheet->setCellValue('B' . $totalsRow, '=SUM(B2:B' . $lastRow . ')');
        
        // Style totals row
        $sheet->getStyle('A' . $totalsRow . ':B' . $totalsRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F5E8'],
            ],
        ]);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Amount column
            'F' => 'mmm dd, yyyy', // Gregorian date
            'I' => 'mmm dd, yyyy hh:mm', // Created at
        ];
    }

    protected function logExport(int $recordCount): void
    {
        ExportLog::create([
            'resource_type' => 'donations',
            'format' => 'xlsx',
            'file_path' => $this->filePath ?? 'exports/donations.xlsx',
            'record_count' => $recordCount,
            'exported_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    public function setFilePath(string $path): self
    {
        $this->filePath = $path;
        return $this;
    }
}
