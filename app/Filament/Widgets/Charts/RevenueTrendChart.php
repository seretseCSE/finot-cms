<?php

namespace App\Filament\Widgets\Charts;

use App\Models\FinancialTransaction;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;

class RevenueTrendChart extends LineChartWidget
{
    protected int | string | array $columnSpan = 2;

    protected ?string $heading = 'Revenue vs Expenses (6 Months)';

    protected function getData(): array
    {
        return Cache::remember('dashboard_revenue_trend', 300, function () {
            $labels = [];
            $incomeData = [];
            $expenseData = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $labels[] = $date->format('M Y');

                $income = FinancialTransaction::income()
                    ->approved()
                    ->whereMonth('transaction_date', $date->month)
                    ->whereYear('transaction_date', $date->year)
                    ->sum('amount');

                $expenses = FinancialTransaction::expense()
                    ->approved()
                    ->whereMonth('transaction_date', $date->month)
                    ->whereYear('transaction_date', $date->year)
                    ->sum('amount');

                $incomeData[] = $income;
                $expenseData[] = $expenses;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Income',
                        'data' => $incomeData,
                        'borderColor' => '#22c55e',
                        'backgroundColor' => '#22c55e',
                    ],
                    [
                        'label' => 'Expenses',
                        'data' => $expenseData,
                        'borderColor' => '#ef4444',
                        'backgroundColor' => '#ef4444',
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }
}
