<?php

namespace App\Filament\Widgets;

use App\Services\SystemMonitoringService;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemHealthWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    public function getHeading(): string
    {
        return 'System Health';
    }

    protected function getStats(): array
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return [];
        }

        $service = new SystemMonitoringService();
        $health = $service->getSystemHealthMetrics();

        return [
            Stat::make('Server Uptime')
                ->value($health['uptime']['formatted'])
                ->description('Load: ' . number_format($health['uptime']['load_average'][0], 2))
                ->descriptionIcon('heroicon-m-server')
                ->color($health['uptime']['status'] == 'critical' ? 'danger' : ($health['uptime']['status'] == 'warning' ? 'warning' : 'success')),

            Stat::make('Storage Usage')
                ->value($health['storage_usage']['percentage'] . '%')
                ->description($health['storage_usage']['used'] . ' / ' . $health['storage_usage']['total'])
                ->descriptionIcon('heroicon-m-hard-disk')
                ->color($health['storage_usage']['status'] == 'critical' ? 'danger' : ($health['storage_usage']['status'] == 'warning' ? 'warning' : 'success')),

            Stat::make('DB Query Time')
                ->value($health['db_query_time'] . 'ms')
                ->description('Average response time')
                ->descriptionIcon('heroicon-m-database')
                ->color($health['db_query_time'] > 100 ? 'danger' : ($health['db_query_time'] > 50 ? 'warning' : 'success')),

            Stat::make('Active Sessions')
                ->value($health['active_sessions'])
                ->description('Currently logged in')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Error Rate')
                ->value($health['error_rate']['rate'] . '%')
                ->description($health['error_rate']['error_logs'] . ' / ' . $health['error_rate']['total_logs'])
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($health['error_rate']['status'] == 'critical' ? 'danger' : ($health['error_rate']['status'] == 'warning' ? 'warning' : 'success')),

            Stat::make('Failed Logins')
                ->value($health['failed_logins'])
                ->description('Total failed attempts')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($health['failed_logins'] > 10 ? 'danger' : ($health['failed_logins'] > 5 ? 'warning' : 'success')),
        ];
    }

    protected function getColumns(): int
    {
        return 6;
    }
}
