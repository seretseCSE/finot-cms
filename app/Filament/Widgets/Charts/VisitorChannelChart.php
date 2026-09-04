<?php

namespace App\Filament\Widgets\Charts;

use App\Services\VisitorAnalyticsService;
use Filament\Widgets\DoughnutChartWidget;

class VisitorChannelChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Traffic channels (30 days)';

    public static function canView(): bool
    {
        return \App\Support\RoleGate::can('analytics.visitors.view')
            || \App\Support\RoleGate::isAny(['admin', 'superadmin', 'av_head']);
    }

    protected function getData(): array
    {
        $channels = collect(app(VisitorAnalyticsService::class)->forDays(30)['channels'])
            ->filter(fn (array $row) => $row['views'] > 0)
            ->values();

        if ($channels->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'data' => [0],
                        'backgroundColor' => ['#e5e7eb'],
                    ],
                ],
                'labels' => ['No data'],
            ];
        }

        $colors = [
            'Direct' => '#64748b',
            'Search' => '#3b82f6',
            'Social' => '#a855f7',
            'Referral' => '#f97316',
        ];

        return [
            'datasets' => [
                [
                    'data' => $channels->pluck('views')->all(),
                    'backgroundColor' => $channels->map(fn (array $row) => $colors[$row['channel']] ?? '#94a3b8')->all(),
                ],
            ],
            'labels' => $channels->pluck('channel')->all(),
        ];
    }
}
