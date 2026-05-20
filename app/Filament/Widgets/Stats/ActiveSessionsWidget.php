<?php

namespace App\Filament\Widgets\Stats;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ActiveSessionsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = DB::table('sessions')->count();

        return [
            Stat::make('Active Sessions', $count)
                ->icon('heroicon-o-globe-alt')
                ->color($count > 0 ? 'success' : 'gray'),
        ];
    }
}
