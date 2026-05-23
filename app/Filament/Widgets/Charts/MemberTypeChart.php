<?php

namespace App\Filament\Widgets\Charts;

use App\Models\Member;
use Filament\Widgets\DoughnutChartWidget;
use Illuminate\Support\Facades\Cache;

class MemberTypeChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Members by Type';

    protected function getData(): array
    {
        $data = Cache::remember('dashboard_member_type_chart', 300, fn () =>
            Member::selectRaw('member_type, COUNT(*) as count')
                ->groupBy('member_type')
                ->pluck('count', 'member_type')
                ->toArray()
        );

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#3b82f6', '#22c55e', '#f97316'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }
}
