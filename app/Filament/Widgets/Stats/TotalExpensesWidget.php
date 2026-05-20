<?php

namespace App\Filament\Widgets\Stats;

use App\Models\FinancialTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class TotalExpensesWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_expenses_mtd_' . now()->format('Y-m');

        $data = Cache::remember($cacheKey, 300, function () {
            $current = FinancialTransaction::expense()
                ->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $previous = FinancialTransaction::expense()
                ->approved()
                ->whereMonth('transaction_date', now()->subMonth()->month)
                ->whereYear('transaction_date', now()->subMonth()->year)
                ->sum('amount');

            $growth = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;

            return ['current' => $current, 'growth' => $growth];
        });

        return [
            Stat::make('Expenses (MTD)', number_format($data['current'], 2) . ' ETB')
                ->description("{$data['growth']}% vs last month")
                ->descriptionIcon($data['growth'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-arrow-trending-down')
                ->color($data['growth'] > 0 ? 'danger' : 'success'),
        ];
    }
}
