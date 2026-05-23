<?php

namespace App\Filament\Widgets\Charts;

use App\Models\MemberGroup;
use Filament\Widgets\BarChartWidget;
use Illuminate\Support\Facades\Cache;

class MembersByGroupChart extends BarChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Members by Group';

    protected function getData(): array
    {
        return Cache::remember('dashboard_members_by_group_chart', 300, function () {
            $groups = MemberGroup::withCount('members')
                ->orderByDesc('members_count')
                ->limit(10)
                ->get();

            return [
                'datasets' => [
                    [
                        'label' => 'Members',
                        'data' => $groups->pluck('members_count')->toArray(),
                        'backgroundColor' => '#3b82f6',
                    ],
                ],
                'labels' => $groups->pluck('name')->toArray(),
            ];
        });
    }
}
