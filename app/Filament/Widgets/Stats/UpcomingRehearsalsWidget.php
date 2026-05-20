<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Rehearsal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UpcomingRehearsalsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Rehearsal::where('date_time', '>=', today())
            ->count();

        return [
            Stat::make('Upcoming Rehearsals', $count)
                ->icon('heroicon-o-calendar')
                ->color('primary'),
        ];
    }
}
