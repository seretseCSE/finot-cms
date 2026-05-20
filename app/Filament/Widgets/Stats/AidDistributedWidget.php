<?php

namespace App\Filament\Widgets\Stats;

use App\Models\AidDistribution;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AidDistributedWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $total = AidDistribution::whereMonth('distribution_date', now()->month)
            ->whereYear('distribution_date', now()->year)
            ->sum('amount');

        return [
            Stat::make('Aid Distributed (MTD)', number_format($total, 2) . ' ETB')
                ->icon('heroicon-o-gift')
                ->color('primary'),
        ];
    }
}
