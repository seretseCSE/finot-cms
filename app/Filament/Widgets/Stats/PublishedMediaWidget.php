<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\MediaResource;
use App\Models\MediaItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PublishedMediaWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Published Media', Cache::remember('dashboard_published_media', 300, fn () => MediaItem::count()))
                ->icon('heroicon-o-photo')
                ->color('primary')
                ->url(MediaResource::getUrl()),
        ];
    }
}
