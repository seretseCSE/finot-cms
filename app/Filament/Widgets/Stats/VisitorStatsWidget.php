<?php

namespace App\Filament\Widgets\Stats;

use App\Models\PageView;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class VisitorStatsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return \App\Support\RoleGate::can('analytics.visitors.view')
            || \App\Support\RoleGate::isAny(['admin', 'superadmin', 'av_head']);
    }

    protected function getStats(): array
    {
        $data = Cache::remember('dashboard_visitor_stats', 300, function () {
            return [
                'today' => PageView::query()->whereDate('created_at', today())->count(),
                'week' => PageView::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'unique' => PageView::query()
                    ->where('created_at', '>=', now()->subDays(7))
                    ->selectRaw('count(distinct session_hash) as c')
                    ->value('c'),
            ];
        });

        return [
            Stat::make('Public visits today', $data['today'])
                ->description($data['week'].' this week · '.$data['unique'].' unique sessions')
                ->icon('heroicon-o-chart-bar')
                ->color('info'),
        ];
    }
}
