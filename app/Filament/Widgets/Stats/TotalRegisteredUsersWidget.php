<?php

namespace App\Filament\Widgets\Stats;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TotalRegisteredUsersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $data = Cache::remember('dashboard_registered_users', 300, function () {
            return [
                'total' => User::count(),
                'active_today' => User::whereDate('last_login_at', today())->count(),
            ];
        });

        return [
            Stat::make('Registered Users', $data['total'])
                ->description("{$data['active_today']} active today")
                ->icon('heroicon-o-user-group')
                ->color('primary'),
        ];
    }
}
