<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The income–expense statement for one scope × window: fee payments + other
 * income by category vs approved expenses by category + payroll cost (gross
 * + employer pension, read-time). Shared by the finance books API and the
 * official PDF statement so the two can never disagree.
 */
class FinanceStatementService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?int $schoolId, ?int $branchId, string $from, string $to): array
    {
        $feeIncome = (float) ($this->scoped(DB::table('payments'), 'payments', $schoolId, $branchId)
            ->whereNull('payments.deleted_at')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(amount), 0) AS total')
            ->value('total') ?? 0);

        $otherIncome = $this->scoped(DB::table('other_incomes'), 'other_incomes', $schoolId, $branchId)
            ->join('finance_categories', 'finance_categories.id', '=', 'other_incomes.finance_category_id')
            ->whereNull('other_incomes.deleted_at')
            ->whereBetween('other_incomes.received_on', [$from, $to])
            ->selectRaw('finance_categories.name, COALESCE(SUM(other_incomes.amount), 0) AS total')
            ->groupBy('finance_categories.name')
            ->orderByRaw('SUM(other_incomes.amount) DESC')
            ->get()
            ->map(fn ($r): array => ['category' => $r->name, 'amount' => number_format((float) $r->total, 2, '.', '')])
            ->all();

        $expenses = $this->scoped(DB::table('expenses'), 'expenses', $schoolId, $branchId)
            ->join('finance_categories', 'finance_categories.id', '=', 'expenses.finance_category_id')
            ->whereNull('expenses.deleted_at')
            ->where('expenses.status', 'approved')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->selectRaw('finance_categories.name, COALESCE(SUM(expenses.amount), 0) AS total')
            ->groupBy('finance_categories.name')
            ->orderByRaw('SUM(expenses.amount) DESC')
            ->get()
            ->map(fn ($r): array => ['category' => $r->name, 'amount' => number_format((float) $r->total, 2, '.', '')])
            ->all();

        $payroll = (float) ($this->scoped(DB::table('payroll_runs'), 'payroll_runs', $schoolId, $branchId)
            ->whereNull('payroll_runs.deleted_at')
            ->whereIn('payroll_runs.status', ['approved', 'paid'])
            ->whereBetween('payroll_runs.period_end', [$from, $to])
            ->selectRaw('COALESCE(SUM(gross_total + pension_employer_total), 0) AS total')
            ->value('total') ?? 0);

        $incomeTotal = $feeIncome + array_sum(array_map(fn ($r) => (float) $r['amount'], $otherIncome));
        $expenseTotal = $payroll + array_sum(array_map(fn ($r) => (float) $r['amount'], $expenses));

        return [
            'income' => [
                'school_fees' => number_format($feeIncome, 2, '.', ''),
                'other' => $otherIncome,
                'total' => number_format($incomeTotal, 2, '.', ''),
            ],
            'expenses' => [
                'payroll' => number_format($payroll, 2, '.', ''),
                'categories' => $expenses,
                'total' => number_format($expenseTotal, 2, '.', ''),
            ],
            'net' => number_format($incomeTotal - $expenseTotal, 2, '.', ''),
        ];
    }

    /** Branch scope wins; else whole school. */
    private function scoped(QueryBuilder $query, string $table, ?int $schoolId, ?int $branchId): QueryBuilder
    {
        return $query
            ->when($branchId, fn ($q) => $q->where("{$table}.branch_id", $branchId))
            ->when(! $branchId && $schoolId, fn ($q) => $q->where("{$table}.school_id", $schoolId));
    }
}
