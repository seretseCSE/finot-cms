<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Song;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalSongsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Songs in Library', Song::count())
                ->icon('heroicon-o-musical-note')
                ->color('primary'),
        ];
    }
}
