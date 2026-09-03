<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\RehearsalResource;
use App\Models\Rehearsal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class UpcomingRehearsalsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_upcoming_rehearsals', 300, fn () =>
            Rehearsal::where('date_time', '>=', today())->count()
        );

        return [
            Stat::make('Upcoming Rehearsals', $count)
                ->icon('heroicon-o-calendar')
                ->color('primary')
                ->url(RehearsalResource::getUrl()),
        ];
    }
}
