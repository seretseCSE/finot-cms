<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Services\Reports\FeeReportService;
use App\Support\Ethiopia;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receivables analytics: overview KPIs + aging, the daily collection
 * (cashier) report, the defaulters register and per-student statements.
 * Scope follows the validated context (`fees.reports.view`): platform =
 * anywhere, school roles = their school (with an optional branch_id
 * narrowing filter), branch roles = their branch.
 */
class FeeReportController extends Controller
{
    use HandlesListQueries;

    public function overview(Request $request, FeeReportService $reports): JsonResponse
    {
        $this->authorizeReports($request);

        return response()->json([
            'data' => $reports->overview($this->invoiceQuery($request), $this->paymentQuery($request)),
        ]);
    }

    public function dailyCollections(Request $request, FeeReportService $reports): JsonResponse
    {
        $this->authorizeReports($request);

        [$from, $to] = $this->collectionWindow($request);

        return response()->json([
            'data' => $reports->dailyCollections(
                $this->paymentQuery($request)->whereBetween('paid_at', [$from, $to]),
            ),
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Students with open balances, worst first — the follow-up register.
     * Row shape: student + open invoice count + balance + overdue amount +
     * oldest overdue due date, hydrated with guardian contacts per page.
     */
    public function defaulters(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $due = Invoice::totalDueSql();
        $today = Ethiopia::today();

        $scoped = $this->invoiceQuery($request);

        $this->applySearch($scoped, $request, fn ($q, string $n) => $q
            ->whereHas('student', fn ($s) => $s->where('search_text', 'ilike', $this->needle($n))));

        $query = $scoped
            ->toBase()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->selectRaw(
                <<<SQL
                student_id,
                COUNT(*) AS open_invoices,
                COALESCE(SUM(({$due}) - amount_paid), 0) AS balance,
                COALESCE(SUM(CASE WHEN due_date < '{$today}' THEN ({$due}) - amount_paid ELSE 0 END), 0) AS overdue_amount,
                MIN(due_date) FILTER (WHERE due_date < '{$today}') AS oldest_due
                SQL,
            )
            ->groupBy('student_id')
            ->havingRaw("COALESCE(SUM(({$due}) - amount_paid), 0) > 0");

        if ($request->boolean('overdue_only')) {
            $query->havingRaw("COALESCE(SUM(CASE WHEN due_date < '{$today}' THEN ({$due}) - amount_paid ELSE 0 END), 0) > 0");
        }

        $sort = in_array($request->string('sort')->value(), ['balance', 'overdue_amount', 'oldest_due', 'open_invoices'], true)
            ? $request->string('sort')->value()
            : 'balance';
        $query->orderByRaw($sort.($request->string('direction')->value() === 'asc' ? ' ASC NULLS LAST' : ' DESC NULLS LAST'));

        $paginator = $query->paginate($this->perPage($request));

        // Hydrate the page's students + guardian contacts in two queries.
        $students = Student::query()
            ->whereIn('id', collect($paginator->items())->pluck('student_id'))
            ->with(['guardians' => fn ($q) => $q->where('is_active', true), 'guardians.parentProfile.user:id,name,phone,email'])
            ->get(['id', 'first_name', 'father_name', 'grandfather_name', 'public_id'])
            ->keyBy('id');

        $rows = collect($paginator->items())->map(function ($row) use ($students): array {
            $student = $students[$row->student_id] ?? null;

            return [
                'student_id' => $row->student_id,
                'student_name' => $student?->full_name,
                'student_public_id' => $student?->public_id,
                'open_invoices' => (int) $row->open_invoices,
                'balance' => number_format((float) $row->balance, 2, '.', ''),
                'overdue_amount' => number_format((float) $row->overdue_amount, 2, '.', ''),
                'oldest_due' => $row->oldest_due,
                'guardians' => $student?->guardians
                    ->map(fn ($link) => $link->parentProfile?->user)
                    ->filter()
                    ->map(fn ($user) => ['name' => $user->name, 'phone' => $user->phone, 'email' => $user->email])
                    ->values()
                    ->all() ?? [],
            ];
        })->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * One student's account statement for the scoped context: every invoice
     * (with payments) plus the running totals a family conference needs.
     */
    public function statement(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $request->validate(['student_id' => ['required', 'integer']]);

        $invoices = $this->invoiceQuery($request)
            ->where('student_id', $request->integer('student_id'))
            ->with([
                'academicYear:id,name',
                'term:id,name',
                'payments.bankAccount.bank:id,code,name,type,logo',
                'concession:id,category,status',
            ])
            ->orderByDesc('created_at')
            ->get();

        $open = $invoices->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'billed' => number_format($invoices->where('status', '!=', InvoiceStatus::Void)->sum(fn (Invoice $i) => $i->totalDue()), 2, '.', ''),
                'paid' => number_format($invoices->where('status', '!=', InvoiceStatus::Void)->sum(fn (Invoice $i) => (float) $i->amount_paid), 2, '.', ''),
                'balance' => number_format($open->sum(fn (Invoice $i) => (float) $i->balance), 2, '.', ''),
                'open_invoices' => $open->count(),
            ],
        ]);
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless($request->user()->hasContextPermission('fees.reports.view'), 403);
    }

    /**
     * Context scoping + shared filters for invoice-based aggregates.
     *
     * @return Builder<Invoice>
     */
    private function invoiceQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return Invoice::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->csvIds($request, 'academic_year_id'), fn ($q, array $ids) => $q->whereIn('academic_year_id', $ids))
            ->when($this->csvIds($request, 'fee_structure_id'), fn ($q, array $ids) => $q->whereIn('fee_structure_id', $ids))
            ->when($this->csvIds($request, 'grade_level_id'), fn ($q, array $ids) => $q
                ->whereHas('student.enrollments', fn ($e) => $e
                    ->whereIn('grade_level_id', $ids)
                    ->whereColumn('student_enrollments.academic_year_id', 'invoices.academic_year_id')));
    }

    /**
     * Context scoping + shared filters for payment-based aggregates.
     *
     * @return Builder<Payment>
     */
    private function paymentQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return Payment::query()
            ->when($branch, fn ($q) => $q->where('payments.branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('payments.school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('payments.branch_id', $id))
            ->when($this->csvIds($request, 'academic_year_id'), fn ($q, array $ids) => $q
                ->whereHas('invoice', fn ($i) => $i->whereIn('academic_year_id', $ids)))
            ->when($this->csvValues($request, 'method'), fn ($q, array $methods) => $q->whereIn('payments.method', $methods))
            ->when($this->csvIds($request, 'bank_account_id'), fn ($q, array $ids) => $q->whereIn('payments.bank_account_id', $ids));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function collectionWindow(Request $request): array
    {
        $to = $request->date('to')?->toDateString() ?? Ethiopia::today();
        $from = $request->date('from')?->toDateString()
            ?? CarbonImmutable::parse($to)->subDays(13)->toDateString();

        return [$from, $to];
    }
}
