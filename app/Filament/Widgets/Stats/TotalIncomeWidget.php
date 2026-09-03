<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\FinancialOverviewPage;
use App\Models\FinancialTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TotalIncomeWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_income_mtd_' . now()->format('Y-m');

        $data = Cache::remember($cacheKey, 300, function () {
            $current = FinancialTransaction::income()
                ->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $previous = FinancialTransaction::income()
                ->approved()
                ->whereMonth('transaction_date', now()->subMonth()->month)
                ->whereYear('transaction_date', now()->subMonth()->year)
                ->sum('amount');

            $growth = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;

            return ['current' => $current, 'growth' => $growth];
        });

        return [
            Stat::make('Income (MTD)', number_format($data['current'], 2) . ' ETB')
                ->description("{$data['growth']}% vs last month")
                ->descriptionIcon($data['growth'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-arrow-trending-up')
                ->color($data['growth'] >= 0 ? 'success' : 'danger')
                ->url(FinancialOverviewPage::getUrl()),
        ];
    }
}
