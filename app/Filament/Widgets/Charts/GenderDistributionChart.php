<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Member;
use Filament\Widgets\DoughnutChartWidget;

class GenderDistributionChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Members by Gender';

    protected function getData(): array
    {
        $data = Member::selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

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
