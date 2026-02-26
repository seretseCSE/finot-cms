<?php

namespace App\Exports;

use App\Models\ExportLog;
use App\Models\Contribution;
use App\Helpers\EthiopianDateHelper;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Style as PhpSpreadsheetStyle;

class ContributionExporter implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected $filters = [];
    protected ?string $filePath = null;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Contribution::with(['member.memberGroup', 'member.schoolClass', 'academicYear', 'recordedBy']);

        // Apply filters
        if (!empty($this->filters['academic_year_id'])) {
            $query->where('academic_year_id', $this->filters['academic_year_id']);
        }

        if (!empty($this->filters['group_ids'])) {
            $query->whereHas('member.memberGroup', function ($q) {
                $q->whereIn('member_groups.id', $this->filters['group_ids']);
            });
        }

        if (!empty($this->filters['class_ids'])) {
            $query->whereHas('member.schoolClass', function ($q) {
                $q->whereIn('school_classes.id', $this->filters['class_ids']);
            });
        }

        if (!empty($this->filters['months'])) {
            $query->whereIn('month_name', $this->filters['months']);
        }

        if (!empty($this->filters['payment_methods'])) {
            $query->whereIn('payment_method', $this->filters['payment_methods']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $this->filters['date_to']);
        }

        $records = $query->orderBy('payment_date', 'desc')->get();

        // Log export
        $this->logExport($records->count());

        return $records;
    }

    public function headings(): array
    {
        return [
            'Member ID',
            'Full Name',
            'Member Code',
            'Group',
            'Class',
            'Month',
            'Amount',
            'Payment Method',
            'Payment Date (Ethiopian)',
            'Payment Date (Gregorian)',
            'Academic Year',
            'Recorded By',
            'Is Archived',
            'Created At',
        ];
    }

    public function map($contribution): array
    {
        // Calculate outstanding amount for conditional formatting
        $expectedAmount = \App\Models\ContributionAmount::where('group_id', $contribution->member->member_group_id)
            ->forMonth($contribution->month_name)
            ->active()
            ->value('amount') ?? 0;

        $outstanding = $expectedAmount - $contribution->amount;

        return [
            $contribution->member_id,
            $contribution->member->full_name,
            $contribution->member->member_code,
            $contribution->member->memberGroup?->name ?? 'N/A',
            $contribution->member->schoolClass?->name ?? 'N/A',
            $contribution->month_name,
            $contribution->amount,
            $contribution->formatted_payment_method,
            app(EthiopianDateHelper::class)->toEthiopian($contribution->payment_date)['month_name_am'] . ' ' . 
            app(EthiopianDateHelper::class)->toEthiopian($contribution->payment_date)['day'] . ', ' .
            app(EthiopianDateHelper::class)->toEthiopian($contribution->payment_date)['year'],
            $contribution->payment_date->format('M d, Y'),
            $contribution->academicYear->name,
            $contribution->recordedBy->name,
            $contribution->is_archived ? 'Yes' : 'No',
            $contribution->created_at->format('M d, Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Freeze header row
        $sheet->freezePane('A2');

        // Style header row
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add conditional formatting for outstanding amounts (column G)
        $conditional = new Conditional();
        $conditional->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
            ->addCondition('0')
            ->setStyle([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFEB3B'],
                ],
                'font' => [
                    'color' => ['rgb' => '000000'],
                ],
            ]);

        // Apply conditional formatting to amount column (G)
        $sheet->getStyle('G2:G' . $sheet->getHighestRow())
            ->setConditionalStyles([$conditional]);

        // Add totals row at the bottom
        $lastRow = $sheet->getHighestRow();
        $totalsRow = $lastRow + 1;
        
        $sheet->setCellValue('F' . $totalsRow, 'TOTAL:');
        $sheet->setCellValue('G' . $totalsRow, '=SUM(G2:G' . $lastRow . ')');
        
        // Style totals row
        $sheet->getStyle('F' . $totalsRow . ':G' . $totalsRow)->applyFromArray([
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
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Amount column
            'J' => 'mmm dd, yyyy', // Gregorian date
            'P' => 'mmm dd, yyyy hh:mm', // Created at
        ];
    }

    protected function logExport(int $recordCount): void
    {
        ExportLog::create([
            'resource_type' => 'contributions',
            'format' => 'xlsx',
            'file_path' => $this->filePath ?? 'exports/contributions.xlsx',
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
