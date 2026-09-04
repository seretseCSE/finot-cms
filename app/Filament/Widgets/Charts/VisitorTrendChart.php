<?php

namespace App\Filament\Widgets\Charts;

use App\Services\VisitorAnalyticsService;
use Filament\Widgets\LineChartWidget;

class VisitorTrendChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Public traffic (30 days)';

    public static function canView(): bool
    {
        return \App\Support\RoleGate::can('analytics.visitors.view')
            || \App\Support\RoleGate::isAny(['admin', 'superadmin', 'av_head']);
    }

    protected function getData(): array
    {
        $trend = app(VisitorAnalyticsService::class)->forDays(30)['trend'];

        return [
            'datasets' => [
                [
                    'label' => 'Pageviews',
                    'data' => array_column($trend, 'pageviews'),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                    'fill' => false,
                ],
                [
                    'label' => 'Unique visitors',
                    'data' => array_column($trend, 'unique'),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => '#22c55e',
                    'fill' => false,
                ],
            ],
            'labels' => array_column($trend, 'label'),
        ];
    }
}
