<?php

namespace App\Filament\Widgets\Stats;

use App\Models\FinancialTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class NetPositionWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 1;

    protected function getStats(): array
    {
        $cacheKey = 'dashboard_net_position_' . now()->format('Y-m');

        $net = Cache::remember($cacheKey, 300, function () {
            $income = FinancialTransaction::income()
                ->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $expenses = FinancialTransaction::expense()
                ->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            return $income - $expenses;
        });

        return [
            Stat::make('Net Position (MTD)', number_format($net, 2) . ' ETB')
                ->icon('heroicon-o-banknotes')
                ->color($net >= 0 ? 'success' : 'danger'),
        ];
    }
}
