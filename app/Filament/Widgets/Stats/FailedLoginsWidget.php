<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\UserResource;
use App\Filament\Support\ClickableStat;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Cache;

class FailedLoginsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_failed_logins', 300, fn () => User::where('failed_login_attempts', '>', 0)->sum('failed_login_attempts'));

        return [
            ClickableStat::make('Failed Logins (24h)', $count, ClickableStat::resourceUrl(UserResource::class))
                ->icon('heroicon-o-shield-exclamation')
                ->color($count > 0 ? 'danger' : 'success'),
        ];
    }
}
