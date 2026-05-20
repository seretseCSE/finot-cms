<?php

namespace App\Filament\Widgets\Charts;

use App\Models\FinancialTransaction;
use Filament\Widgets\DoughnutChartWidget;
use Illuminate\Support\Facades\Cache;

class ExpenseBreakdownChart extends DoughnutChartWidget
{
    protected int | string | array $columnSpan = 1;

    protected ?string $heading = 'Expenses by Category';

    protected function getData(): array
    {
        $expenses = Cache::remember('dashboard_expense_breakdown', 300, function () {
            return FinancialTransaction::expense()
                ->whereYear('transaction_date', now()->year)
                ->selectRaw('COALESCE(category, "Uncategorized") as category, SUM(amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->toArray();
        });

        if (empty($expenses)) {
            return [
                'datasets' => [
                    [
                        'data' => [0],
                        'backgroundColor' => ['#e5e7eb'],
                    ],
                ],
                'labels' => ['No data'],
            ];
        }

        $colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f43f5e', '#a855f7'];

        return [
            'datasets' => [
                [
                    'data' => array_map(fn ($e) => (float) $e['total'], $expenses),
                    'backgroundColor' => array_slice($colors, 0, count($expenses)),
                ],
            ],
            'labels' => array_map(fn ($e) => $e['category'], $expenses),
        ];
    }
}
