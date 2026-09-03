<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\ManageActiveSessions;
use App\Filament\Support\ClickableStat;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActiveSessionsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_active_sessions', 60, fn () => DB::table('sessions')->count());

        return [
            ClickableStat::make('Active Sessions', $count, ClickableStat::pageUrl(ManageActiveSessions::class))
                ->icon('heroicon-o-globe-alt')
                ->color($count > 0 ? 'success' : 'gray'),
        ];
    }
}
