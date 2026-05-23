<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\FinancialTransaction;
use App\Models\Member;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DashboardCacheWarmCommand extends Command
{
    protected $signature = 'dashboard:cache-warm';

    protected $description = 'Pre-warm all dashboard widget caches for faster page loads';

    public function handle(): int
    {
        $this->info('Warming dashboard caches...');

        Cache::remember('dashboard_total_members', 300, fn () => Member::count());
        $this->line('  - Total members');

        $month = now()->format('Y-m');
        Cache::remember("dashboard_income_mtd_{$month}", 300, function () {
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
        $this->line('  - Income (MTD)');

        Cache::remember("dashboard_expenses_mtd_{$month}", 300, function () {
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
        $this->line('  - Expenses (MTD)');

        Cache::remember("dashboard_contributions_mtd_{$month}", 300, function () {
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
        $this->line('  - Contributions (MTD)');

        Cache::remember('dashboard_revenue_trend', 300, function () {
            $incomeData = [];
            $expenseData = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);

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
                    ['label' => 'Income', 'data' => $incomeData, 'borderColor' => '#22c55e', 'backgroundColor' => '#22c55e'],
                    ['label' => 'Expenses', 'data' => $expenseData, 'borderColor' => '#ef4444', 'backgroundColor' => '#ef4444'],
                ],
                'labels' => [now()->subMonths(5)->format('M Y'), now()->subMonths(4)->format('M Y'), now()->subMonths(3)->format('M Y'), now()->subMonths(2)->format('M Y'), now()->subMonths(1)->format('M Y'), now()->format('M Y')],
            ];
        });
        $this->line('  - Revenue trend chart');

        Cache::remember('dashboard_expense_breakdown', 300, function () {
            return FinancialTransaction::expense()
                ->whereYear('transaction_date', now()->year)
                ->selectRaw('COALESCE(category, "Uncategorized") as category, SUM(amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->toArray();
        });
        $this->line('  - Expense breakdown chart');

        $this->newLine();
        $this->info('Dashboard caches warmed successfully.');

        return Command::SUCCESS;
    }
}
