<?php

namespace App\Filament\Widgets\Stats;

use App\Enums\MemberType;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TotalMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Cache::remember('dashboard_total_members', 300, fn () => Member::count());

        return [
            Stat::make('Total Members', $count)
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }
}
