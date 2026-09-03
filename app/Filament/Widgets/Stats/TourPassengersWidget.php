<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\TourPassengerResource;
use App\Models\TourPassenger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TourPassengersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_tour_passengers', 300, fn () =>
            TourPassenger::whereYear('created_at', now()->year)->count()
        );

        return [
            Stat::make('Passengers (YTD)', $count)
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(TourPassengerResource::getUrl()),
        ];
    }
}
