<?php

namespace App\Filament\Widgets;

use App\Models\BlogPost;
use App\Models\Announcement;
use App\Models\Song;
use App\Models\MediaItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ContentSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Only show to AV Head, Admin, Superadmin
        if (!Auth::user()?->hasRole(['av_head', 'admin', 'superadmin'])) {
            return [];
        }

        return [
            Stat::make('Published Posts', BlogPost::where('status', 'Published')->count())
                ->description('Total published blog posts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success'),

            Stat::make('Active Announcements', Announcement::where('status', 'Active')->count())
                ->description('Currently active announcements')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('warning'),

            Stat::make('Total Songs', Song::where('is_active', true)->count())
                ->description('Active songs in library')
                ->descriptionIcon('heroicon-m-musical-note')
                ->color('primary'),

            Stat::make('Media Items', MediaItem::whereNull('deleted_at')->count())
                ->description('Total media items')
                ->descriptionIcon('heroicon-m-photo')
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}

