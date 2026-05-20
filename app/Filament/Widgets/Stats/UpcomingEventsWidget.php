<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UpcomingEventsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Event::where('start_date', '>=', today())->count();

        return [
            Stat::make('Upcoming Events', $count)
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),
        ];
    }
}
