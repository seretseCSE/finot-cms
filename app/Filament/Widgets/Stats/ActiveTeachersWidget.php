<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ActiveTeachersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Active Teachers', Cache::remember('dashboard_active_teachers', 300, fn () => Teacher::count()))
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
