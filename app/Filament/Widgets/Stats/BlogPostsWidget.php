<?php

namespace App\Filament\Widgets\Stats;

use App\Models\BlogPost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BlogPostsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = BlogPost::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            Stat::make('Posts This Month', $count)
                ->icon('heroicon-o-document-text')
                ->color('primary'),
        ];
    }
}
