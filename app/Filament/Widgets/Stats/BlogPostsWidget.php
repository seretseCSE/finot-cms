<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\BlogPostResource;
use App\Models\BlogPost;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class BlogPostsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_blog_posts_' . now()->format('Y_m'), 300, fn () =>
            BlogPost::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count()
        );

        return [
            Stat::make('Posts This Month', $count)
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(BlogPostResource::getUrl()),
        ];
    }
}
