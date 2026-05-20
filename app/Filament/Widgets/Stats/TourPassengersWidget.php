<?php

namespace App\Filament\Widgets\Stats;

use App\Models\TourPassenger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TourPassengersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = TourPassenger::whereYear('created_at', now()->year)->count();

        return [
            Stat::make('Passengers (YTD)', $count)
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
