<?php

namespace App\Filament\Widgets\Stats;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class FailedLoginsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_failed_logins', 300, fn () => User::where('failed_login_attempts', '>', 0)->sum('failed_login_attempts'));

        return [
            Stat::make('Failed Logins (24h)', $count)
                ->icon('heroicon-o-shield-exclamation')
                ->color($count > 0 ? 'danger' : 'success'),
        ];
    }
}
