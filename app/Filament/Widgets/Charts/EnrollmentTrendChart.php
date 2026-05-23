<?php

namespace App\Filament\Widgets\Charts;

use App\Models\StudentEnrollment;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;

class EnrollmentTrendChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Enrollment Trend';

    protected function getData(): array
    {
        return Cache::remember('dashboard_enrollment_trend_chart', 300, function () {
            $data = StudentEnrollment::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn ($item) => [
                    'label' => date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year)),
                    'count' => $item->count,
                ]);

            return [
                'datasets' => [
                    [
                        'label' => 'Enrollments',
                        'data' => $data->pluck('count')->toArray(),
                        'borderColor' => '#8b5cf6',
                        'backgroundColor' => '#8b5cf6',
                        'fill' => true,
                    ],
                ],
                'labels' => $data->pluck('label')->toArray(),
            ];
        });
    }
}
