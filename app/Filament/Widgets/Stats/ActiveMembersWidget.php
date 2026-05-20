<?php

namespace App\Filament\Widgets\Stats;

use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveMembersWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $count = Member::where('status', 'Active')->count();
        $total = Member::count();
        $rate = $total > 0 ? round(($count / $total) * 100, 1) : 0;

        return [
            Stat::make('Active Members', $count)
                ->description("{$rate}% of total")
                ->icon('heroicon-o-user-group')
                ->color('success'),
        ];
    }
}
