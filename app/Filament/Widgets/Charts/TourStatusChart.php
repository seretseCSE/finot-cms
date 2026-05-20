<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Tour;
use Filament\Widgets\DoughnutChartWidget;

class TourStatusChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Tours by Status';

    protected function getData(): array
    {
        $data = Tour::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#22c55e', '#3b82f6', '#f97316', '#6b7280'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
}
