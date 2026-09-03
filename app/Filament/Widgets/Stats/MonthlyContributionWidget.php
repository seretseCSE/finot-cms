<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\ContributionReport;
use App\Models\Contribution;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class MonthlyContributionWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_contributions_mtd_' . now()->format('Y-m');

        $data = Cache::remember($cacheKey, 300, function () {
            $total = Contribution::where('is_paid', true)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');

            $count = Contribution::where('is_paid', true)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->distinct('member_id')
                ->count('member_id');

            return ['total' => $total, 'count' => $count];
        });

        return [
            Stat::make('Contributions (MTD)', number_format($data['total'], 2) . ' ETB')
                ->description("{$data['count']} contributors")
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->url(ContributionReport::getUrl()),
        ];
    }
}
