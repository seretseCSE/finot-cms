<?php

namespace App\Filament\Widgets\Stats;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalRegisteredUsersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $total = User::count();
        $activeToday = User::whereDate('last_login_at', today())->count();

        return [
            Stat::make('Registered Users', $total)
                ->description("{$activeToday} active today")
                ->icon('heroicon-o-user-group')
                ->color('primary'),
        ];
    }
}
