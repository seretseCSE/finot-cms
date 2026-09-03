<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Resources\AidDistributionResource;
use App\Models\AidDistribution;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AidDistributedWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $total = Cache::remember('dashboard_aid_distributed_' . now()->format('Y_m'), 300, fn () =>
            AidDistribution::whereMonth('distribution_date', now()->month)
                ->whereYear('distribution_date', now()->year)
                ->sum('amount')
        );

        return [
            Stat::make('Aid Distributed (MTD)', number_format($total, 2) . ' ETB')
                ->icon('heroicon-o-gift')
                ->color('primary')
                ->url(AidDistributionResource::getUrl()),
        ];
    }
}
