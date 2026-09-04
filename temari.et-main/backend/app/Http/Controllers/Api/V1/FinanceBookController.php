<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Budget;
use App\Models\FinanceCategory;
use App\Services\Reports\FinanceStatementService;
use App\Support\Ethiopia;
use App\Support\SearchTerm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The books: the unified cashbook (every birr in and out, in one dated
 * ledger), the income–expense statement by category, and budget vs actual.
 * Money in = fee payments + other income; money out = APPROVED expenses +
 * approved/paid payroll runs (payroll cost = gross + employer pension,
 * read-time — payroll is never re-recorded as an expense row).
 */
class FinanceBookController extends Controller
{
    use HandlesListQueries;

    /** One dated ledger over payments / other income / expenses / payroll. */
    public function cashbook(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('finance.books.view'), 403);

        [$from, $to] = $this->window($request);

        $union = $this->paymentsLedger($request, $from, $to)
            ->unionAll($this->incomesLedger($request, $from, $to))
            ->unionAll($this->expensesLedger($request, $from, $to))
            ->unionAll($this->payrollLedger($request, $from, $to));

        // Search / direction / source / category narrow the union AFTER the
        // legs, so the totals strip always matches exactly what the table shows.
        $filtered = fn () => DB::query()->fromSub($union, 'ledger')
            ->tap(fn ($q) => SearchTerm::apply($q, $request->string('search')->trim()->value(), fn ($w, string $n) => $w
                ->where('description', 'ilike', SearchTerm::contains($n))))
            ->when(
                in_array($request->string('direction')->value(), ['in', 'out'], true),
                fn ($q) => $q->where('direction', $request->string('direction')->value()),
            )
            ->when($this->csvValues($request, 'source'), fn ($q, array $s) => $q->whereIn('source', $s))
            ->when(
                $this->csvIds($request, 'finance_category_id'),
                fn ($q, array $ids) => $q->whereIn('finance_category_id', $ids),
            );

        $sort = $request->input('sort') === 'amount' ? 'amount' : 'entry_date';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $rows = $filtered()
            ->orderBy($sort, $dir)
            ->when($sort !== 'entry_date', fn ($q) => $q->orderByDesc('entry_date'))
            ->orderByDesc('entry_id')
            ->paginate($this->perPage($request));

        $totals = $filtered()->selectRaw(
            <<<'SQL'
            COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) AS money_in,
            COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) AS money_out
            SQL,
        )->first();

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $from,
                'to' => $to,
                'money_in' => number_format((float) ($totals->money_in ?? 0), 2, '.', ''),
                'money_out' => number_format((float) ($totals->money_out ?? 0), 2, '.', ''),
                'net' => number_format((float) ($totals->money_in ?? 0) - (float) ($totals->money_out ?? 0), 2, '.', ''),
            ],
        ]);
    }

    /** Income–expense statement by category for a window. */
    public function statement(Request $request, FinanceStatementService $statements): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('finance.books.view'), 403);

        [$from, $to] = $this->window($request);
        [$schoolId, $branchId] = $this->statementScope($request);

        return response()->json([
            'data' => $statements->build($schoolId, $branchId, $from, $to),
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * The (school, branch) pair the statement covers, mirroring scoped():
     * branch context wins, else the whole school with an optional branch
     * narrowing filter.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function statementScope(Request $request): array
    {
        $branch = $this->activeBranchOrNull($request);

        if ($branch !== null) {
            return [(int) $branch->school_id, (int) $branch->id];
        }

        return [
            $this->activeSchoolScopeId($request),
            $this->branchFilterId($request, null) ?: null,
        ];
    }

    /** Budget vs actual per expense category for one branch × year. */
    public function budgets(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('finance.books.view'), 403);

        [$branchId, $year] = $this->budgetAnchor($request);

        $budgets = Budget::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $year->id)
            ->pluck('amount', 'finance_category_id');

        // GAPLESS actuals window: a budget year owns every expense from its
        // start up to the day before the NEXT year starts (open-ended for the
        // latest year). Anchoring on ends_on would orphan kremt (Jul–Aug)
        // spending — schools absolutely spend between academic years.
        [$windowFrom, $windowTo] = $this->budgetWindow($branchId, $year);

        $sums = DB::table('expenses')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereNull('deleted_at')
            ->whereBetween('expense_date', [$windowFrom, $windowTo])
            ->selectRaw('finance_category_id, status, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('finance_category_id', 'status')
            ->get();

        $actuals = $sums->where('status', 'approved')->pluck('total', 'finance_category_id');
        $pendings = $sums->where('status', 'pending')->pluck('total', 'finance_category_id');

        $categories = FinanceCategory::query()
            ->where('school_id', $year->school_id)
            ->where('kind', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FinanceCategory $c): array => [
                'finance_category_id' => $c->id,
                'category' => $c->name,
                'budget' => isset($budgets[$c->id]) ? number_format((float) $budgets[$c->id], 2, '.', '') : null,
                'actual' => number_format((float) ($actuals[$c->id] ?? 0), 2, '.', ''),
                'pending' => number_format((float) ($pendings[$c->id] ?? 0), 2, '.', ''),
            ]);

        return response()->json([
            'data' => $categories,
            'meta' => [
                'branch_id' => $branchId,
                'academic_year_id' => $year->id,
                'academic_year_name' => $year->name,
                'window_from' => $windowFrom,
                'window_to' => $windowTo === '2999-12-31' ? null : $windowTo,
                'budget_total' => number_format((float) $budgets->sum(), 2, '.', ''),
                'actual_total' => number_format((float) $actuals->sum(), 2, '.', ''),
                'pending_total' => number_format((float) $pendings->sum(), 2, '.', ''),
            ],
        ]);
    }

    /** Upsert budget cells for one branch × year. */
    public function saveBudgets(Request $request): JsonResponse
    {
        [$branchId, $year] = $this->budgetAnchor($request);

        abort_unless(
            $request->user()->hasPermissionForScope('finance.books.manage', $year->school_id, $branchId),
            403,
        );

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.finance_category_id' => [
                'required',
                'integer',
                Rule::exists('finance_categories', 'id')
                    ->where('school_id', $year->school_id)
                    ->where('kind', 'expense'),
            ],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data['items'] as $item) {
            if ($item['amount'] === null || $item['amount'] === '') {
                Budget::query()
                    ->where('branch_id', $branchId)
                    ->where('academic_year_id', $year->id)
                    ->where('finance_category_id', $item['finance_category_id'])
                    ->delete();

                continue;
            }

            Budget::withTrashed()->updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'academic_year_id' => $year->id,
                    'finance_category_id' => $item['finance_category_id'],
                ],
                [
                    'school_id' => $year->school_id,
                    'amount' => $item['amount'],
                    'note' => $item['note'] ?? null,
                    'deleted_at' => null,
                ],
            );
        }

        return response()->json(['message' => 'Budget saved.']);
    }

    /**
     * The date span whose expenses count against this budget year: starts_on
     * up to the day before the next year's starts_on (same branch), or
     * open-ended when no later year exists yet.
     *
     * @return array{0: string, 1: string}
     */
    private function budgetWindow(int $branchId, AcademicYear $year): array
    {
        $from = $year->starts_on?->toDateString() ?? '1900-01-01';

        $nextStart = AcademicYear::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('starts_on')
            ->where('starts_on', '>', $from)
            ->min('starts_on');

        $to = $nextStart
            ? CarbonImmutable::parse($nextStart)->subDay()->toDateString()
            : '2999-12-31';

        return [$from, $to];
    }

    /**
     * @return array{0: int, 1: AcademicYear}
     */
    private function budgetAnchor(Request $request): array
    {
        $branch = $this->activeBranchOrNull($request);
        $branchId = $branch?->id ?? $request->integer('branch_id');

        if (! $branchId) {
            throw ValidationException::withMessages(['branch_id' => ['Pick the branch this budget belongs to.']]);
        }

        $year = AcademicYear::query()
            ->where('branch_id', $branchId)
            ->findOrFail($request->integer('academic_year_id'));

        return [(int) $branchId, $year];
    }

    // ── ledger legs ────────────────────────────────────────────────────

    private function paymentsLedger(Request $request, string $from, string $to): QueryBuilder
    {
        return $this->scoped($request, DB::table('payments'), 'payments')
            ->leftJoin('branches', 'branches.id', '=', 'payments.branch_id')
            ->whereNull('payments.deleted_at')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->selectRaw(
                <<<'SQL'
                payments.paid_at AS entry_date,
                payments.id AS entry_id,
                'fee_payment' AS source,
                'in' AS direction,
                CONCAT('Fee payment · ', payments.receipt_number) AS description,
                payments.method AS method,
                payments.bank_account_id AS bank_account_id,
                NULL::bigint AS finance_category_id,
                NULL AS category,
                branches.name AS branch_name,
                payments.amount AS amount
                SQL,
            )
            ->when($this->csvValues($request, 'method'), fn ($q, array $m) => $q->whereIn('payments.method', $m))
            ->when($this->csvIds($request, 'bank_account_id'), fn ($q, array $ids) => $q->whereIn('payments.bank_account_id', $ids));
    }

    private function incomesLedger(Request $request, string $from, string $to): QueryBuilder
    {
        return $this->scoped($request, DB::table('other_incomes'), 'other_incomes')
            ->leftJoin('finance_categories', 'finance_categories.id', '=', 'other_incomes.finance_category_id')
            ->leftJoin('branches', 'branches.id', '=', 'other_incomes.branch_id')
            ->whereNull('other_incomes.deleted_at')
            ->whereBetween('other_incomes.received_on', [$from, $to])
            ->selectRaw(
                <<<'SQL'
                other_incomes.received_on AS entry_date,
                other_incomes.id AS entry_id,
                'other_income' AS source,
                'in' AS direction,
                other_incomes.title AS description,
                other_incomes.method AS method,
                other_incomes.bank_account_id AS bank_account_id,
                other_incomes.finance_category_id AS finance_category_id,
                finance_categories.name AS category,
                branches.name AS branch_name,
                other_incomes.amount AS amount
                SQL,
            )
            ->when($this->csvValues($request, 'method'), fn ($q, array $m) => $q->whereIn('other_incomes.method', $m))
            ->when($this->csvIds($request, 'bank_account_id'), fn ($q, array $ids) => $q->whereIn('other_incomes.bank_account_id', $ids));
    }

    private function expensesLedger(Request $request, string $from, string $to): QueryBuilder
    {
        return $this->scoped($request, DB::table('expenses'), 'expenses')
            ->leftJoin('finance_categories', 'finance_categories.id', '=', 'expenses.finance_category_id')
            ->leftJoin('branches', 'branches.id', '=', 'expenses.branch_id')
            ->whereNull('expenses.deleted_at')
            ->where('expenses.status', 'approved')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->selectRaw(
                <<<'SQL'
                expenses.expense_date AS entry_date,
                expenses.id AS entry_id,
                'expense' AS source,
                'out' AS direction,
                expenses.title AS description,
                expenses.method AS method,
                expenses.bank_account_id AS bank_account_id,
                expenses.finance_category_id AS finance_category_id,
                finance_categories.name AS category,
                branches.name AS branch_name,
                expenses.amount AS amount
                SQL,
            )
            ->when($this->csvValues($request, 'method'), fn ($q, array $m) => $q->whereIn('expenses.method', $m))
            ->when($this->csvIds($request, 'bank_account_id'), fn ($q, array $ids) => $q->whereIn('expenses.bank_account_id', $ids));
    }

    private function payrollLedger(Request $request, string $from, string $to): QueryBuilder
    {
        // Payroll has no method/account; a method or account filter excludes it.
        $filtered = $this->csvValues($request, 'method') !== [] || $this->csvIds($request, 'bank_account_id') !== [];

        return $this->scoped($request, DB::table('payroll_runs'), 'payroll_runs')
            ->leftJoin('branches', 'branches.id', '=', 'payroll_runs.branch_id')
            ->whereNull('payroll_runs.deleted_at')
            ->whereIn('payroll_runs.status', ['approved', 'paid'])
            ->whereBetween('payroll_runs.period_end', [$from, $to])
            ->when($filtered, fn ($q) => $q->whereRaw('1 = 0'))
            ->selectRaw(
                <<<'SQL'
                payroll_runs.period_end AS entry_date,
                payroll_runs.id AS entry_id,
                'payroll' AS source,
                'out' AS direction,
                CONCAT('Payroll · ', payroll_runs.name) AS description,
                NULL AS method,
                NULL::bigint AS bank_account_id,
                NULL::bigint AS finance_category_id,
                NULL AS category,
                branches.name AS branch_name,
                (payroll_runs.gross_total + payroll_runs.pension_employer_total) AS amount
                SQL,
            );
    }

    /** Tenant scope shared by every leg (branch / school / branch filter). */
    private function scoped(Request $request, QueryBuilder $query, string $table): QueryBuilder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return $query
            ->when($branch, fn ($q) => $q->where("{$table}.branch_id", $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where("{$table}.school_id", $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where("{$table}.branch_id", $id));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function window(Request $request): array
    {
        $to = $request->date('to')?->toDateString() ?? Ethiopia::today();
        $from = $request->date('from')?->toDateString()
            ?? CarbonImmutable::parse($to)->subDays(29)->toDateString();

        return [$from, $to];
    }
}
