<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\SongResource;
use App\Models\Song;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TotalSongsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_total_songs', 300, fn () => Song::count());

        return [
            Stat::make('Songs in Library', $count)
                ->icon('heroicon-o-musical-note')
                ->color('primary')
                ->url(SongResource::getUrl()),
        ];
    }
}
