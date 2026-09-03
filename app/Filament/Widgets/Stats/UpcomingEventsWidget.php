<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class UpcomingEventsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_upcoming_events', 300, fn () => Event::where('date_time', '>=', today())->count());

        return [
            Stat::make('Upcoming Events', $count)
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->url(EventResource::getUrl()),
        ];
    }
}
