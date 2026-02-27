<?php

namespace App\Filament\Widgets;

use App\Services\SystemMonitoringService;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GlobalOversightStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return [];
        }

        $service = new SystemMonitoringService();
        $stats = $service->getSystemOverviewStats();

        return [
            Stat::make('Total Members')
                ->value(number_format($stats['total_members']))
                ->description('All registered members')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart([7, 12, 10, 14, 15, 18, 20]),

            Stat::make('Contributions This Year')
                ->value('ETB ' . number_format($stats['contributions_this_year'], 2))
                ->description('Total contributions in ' . now()->year)
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart([1000, 1200, 900, 1500, 1800, 1600, 2000]),

            Stat::make('Active Tours')
                ->value(number_format($stats['active_tours']))
                ->description('Currently active tours')
                ->descriptionIcon('heroicon-m-map')
                ->color('warning')
                ->chart([3, 5, 4, 6, 7, 5, 8]),

            Stat::make('Total Users')
                ->value(number_format($stats['total_users']))
                ->description('System users')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info')
                ->chart([10, 15, 13, 18, 20, 22, 25]),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
