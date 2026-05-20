<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveTeachersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Active Teachers', Teacher::count())
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
