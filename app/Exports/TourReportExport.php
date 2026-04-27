<?php

namespace App\Exports;

use App\Helpers\EthiopianDateHelper;
use App\Models\Tour;
use App\Services\ExportAuditService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TourReportExport implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected array $filters = [];

    protected Collection $tours;

    protected array $metrics = [];

    public function __construct(array $filters = [], array $metrics = [])
    {
        $this->filters = $filters;
        $this->metrics = $metrics;
    }

    public function collection()
    {
        $query = Tour::query()
            ->when($this->filters['status'] ?? 'all' !== 'all', function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when(($this->filters['date_range'] ?? 'all') !== 'all', function ($query) {
                $dateFilter = match ($this->filters['date_range']) {
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                $query->where('tour_date', '>=', $dateFilter);
            })
            ->with(['passengers'])
            ->orderBy('tour_date', 'desc');

        $this->tours = $query->get();

        ExportAuditService::log(
            resourceType: 'tour_reports',
            format: 'xlsx',
            recordCount: $this->tours->count(),
            filters: $this->filters,
        );

        return $this->tours;
    }

    public function headings(): array
    {
        return [
            'Tour Place',
            'Date (Ethiopian)',
            'Date (Gregorian)',
            'Status',
            'Total Passengers',
            'Confirmed',
            'Attended',
            'Attendance Rate',
            'Cost Per Person',
            'Total Revenue',
        ];
    }

    public function map($tour): array
    {
        $totalPassengers = $tour->passengers->sum('passenger_count');
        $confirmed = $tour->passengers->where('status', 'Confirmed')->sum('passenger_count');
        $attended = $tour->passengers->where('status', 'Attended')->sum('passenger_count');
        $attendanceRate = $totalPassengers > 0 ? round(($attended / $totalPassengers) * 100, 1) : 0;
        $revenue = $confirmed * ($tour->cost_per_person ?? 0);

        $ethiopianDate = app(EthiopianDateHelper::class)->toEthiopian($tour->tour_date);

        return [
            $tour->place,
            $ethiopianDate['month_name_am'].' '.$ethiopianDate['day'].', '.$ethiopianDate['year'],
            $tour->tour_date->format('M d, Y'),
            $tour->status,
            $totalPassengers,
            $confirmed,
            $attended,
            $attendanceRate.'%',
            $tour->cost_per_person ?? 0,
            $revenue,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Freeze header row
        $sheet->freezePane('A2');

        // Style header row
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1B4F72'],
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add summary metrics section after data
        $lastRow = $sheet->getHighestRow();
        $summaryStart = $lastRow + 2;

        // Summary title
        $sheet->setCellValue('A'.$summaryStart, 'Summary Metrics');
        $sheet->getStyle('A'.$summaryStart)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F4FD'],
            ],
        ]);
        $sheet->mergeCells('A'.$summaryStart.':J'.$summaryStart);

        $metrics = $this->metrics;
        $row = $summaryStart + 1;

        $sheet->setCellValue('A'.$row, 'Total Tours:');
        $sheet->setCellValue('B'.$row, $metrics['total_tours'] ?? $this->tours->count());
        $row++;

        $sheet->setCellValue('A'.$row, 'Completed Tours:');
        $sheet->setCellValue('B'.$row, $metrics['completed_tours'] ?? $this->tours->where('status', 'Completed')->count());
        $row++;

        $sheet->setCellValue('A'.$row, 'Total Passengers:');
        $sheet->setCellValue('B'.$row, $metrics['total_passengers'] ?? $this->tours->sum(fn ($t) => $t->passengers->sum('passenger_count')));
        $row++;

        $sheet->setCellValue('A'.$row, 'Confirmed Passengers:');
        $sheet->setCellValue('B'.$row, $metrics['confirmed_passengers'] ?? $this->tours->sum(fn ($t) => $t->passengers->where('status', 'Confirmed')->sum('passenger_count')));
        $row++;

        $sheet->setCellValue('A'.$row, 'Attended Passengers:');
        $sheet->setCellValue('B'.$row, $metrics['attended_passengers'] ?? $this->tours->sum(fn ($t) => $t->passengers->where('status', 'Attended')->sum('passenger_count')));
        $row++;

        $sheet->setCellValue('A'.$row, 'Total Revenue:');
        $sheet->setCellValue('B'.$row, $metrics['total_revenue'] ?? $this->tours->sum(fn ($t) => $t->passengers->where('status', 'Confirmed')->sum('passenger_count') * ($t->cost_per_person ?? 0)));
        $row++;

        $sheet->setCellValue('A'.$row, 'Average Attendance Rate:');
        $avgRate = $metrics['average_attendance_rate'] ?? ($this->tours->count() > 0 ? round($this->tours->avg(function ($t) {
            $total = $t->passengers->sum('passenger_count');
            $attended = $t->passengers->where('status', 'Attended')->sum('passenger_count');

            return $total > 0 ? ($attended / $total) * 100 : 0;
        }), 1) : 0);
        $sheet->setCellValue('B'.$row, $avgRate.'%');

        // Style metric labels
        $sheet->getStyle('A'.($summaryStart + 1).':A'.$row)->applyFromArray([
            'font' => ['bold' => true],
        ]);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => 'mmm dd, yyyy', // Gregorian date
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Cost
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Revenue
        ];
    }
}
