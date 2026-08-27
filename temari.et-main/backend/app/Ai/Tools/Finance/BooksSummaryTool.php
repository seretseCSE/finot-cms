<?php

namespace App\Ai\Tools\Finance;

use App\Ai\Tools\Leadership\LeadershipScopedTool;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\OtherIncome;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The cash books: expenses by category (with pending approvals shown
 * separately, mirroring the budget-vs-actual screens), other income, and
 * budget positions for the active year.
 */
class BooksSummaryTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Expense and income books for the active year: expenses by category (approved vs pending approval), other income by category, and budget vs actual (ETB). Use for spending, budget, or income questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('finance.books.view', 'finance.books.manage')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $expenses = Expense::query()
            ->whereIn('branch_id', $branchIds)
            ->where('expense_date', '>=', now()->subYear()->toDateString())
            ->join('finance_categories', 'finance_categories.id', '=', 'expenses.finance_category_id')
            ->selectRaw("finance_categories.name as category,
                sum(amount) filter (where expenses.status = 'approved') as approved,
                sum(amount) filter (where expenses.status = 'pending') as pending")
            ->groupBy('finance_categories.name')
            ->orderByRaw('sum(amount) desc')
            ->limit(30)
            ->get()
            ->map(fn ($row): array => [
                'category' => $row->category,
                'approved_etb' => (float) ($row->approved ?? 0),
                'pending_approval_etb' => (float) ($row->pending ?? 0),
            ]);

        $income = OtherIncome::query()
            ->whereIn('branch_id', $branchIds)
            ->where('received_on', '>=', now()->subYear()->toDateString())
            ->join('finance_categories', 'finance_categories.id', '=', 'other_incomes.finance_category_id')
            ->selectRaw('finance_categories.name as category, sum(amount) as total')
            ->groupBy('finance_categories.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => ['category' => $row->category, 'total_etb' => (float) $row->total]);

        $budgets = Budget::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->with('category:id,name')
            ->get()
            ->map(fn (Budget $budget): array => [
                'category' => $budget->category?->name,
                'budget_etb' => (float) $budget->amount,
            ]);

        return $this->ok([
            'expenses_last_12_months' => $expenses,
            'other_income_last_12_months' => $income,
            'active_year_budgets' => $budgets,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
