<?php

namespace App\Filament\Widgets\Stats;

use App\Models\MediaItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PublishedMediaWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Published Media', MediaItem::count())
                ->icon('heroicon-o-photo')
                ->color('primary'),
        ];
    }
}
