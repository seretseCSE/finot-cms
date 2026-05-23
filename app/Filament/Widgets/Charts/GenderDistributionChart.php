<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Member;
use Filament\Widgets\DoughnutChartWidget;
use Illuminate\Support\Facades\Cache;

class GenderDistributionChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Members by Gender';

    protected function getData(): array
    {
        $data = Cache::remember('dashboard_gender_distribution_chart', 300, fn () =>
            Member::selectRaw('gender, COUNT(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray()
        );

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#3b82f6', '#ec4899'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
}
