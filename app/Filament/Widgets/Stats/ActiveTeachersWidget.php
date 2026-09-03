<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\TeacherResource;
use App\Filament\Support\ClickableStat;
use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Cache;

class ActiveTeachersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            ClickableStat::make('Active Teachers', Cache::remember('dashboard_active_teachers', 300, fn () => Teacher::count()), ClickableStat::resourceUrl(TeacherResource::class))
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
