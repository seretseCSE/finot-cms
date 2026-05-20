<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Tour;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UpcomingToursWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Tour::where('tour_date', '>=', today())
            ->whereIn('status', ['active', 'scheduled'])
            ->count();

        return [
            Stat::make('Upcoming Tours', $count)
                ->icon('heroicon-o-truck')
                ->color('primary'),
        ];
    }
}
