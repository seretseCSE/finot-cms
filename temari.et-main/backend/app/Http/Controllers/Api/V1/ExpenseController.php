<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\School;
use App\Services\Notify\Notifier;
use App\Support\Ethiopia;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Money out. Finance records (finance.books.manage), a countersigner
 * approves (finance.books.approve) — never their own row. Only pending rows
 * can be edited or deleted; approved history is immutable (the cashbook and
 * statement read approved rows only).
 */
class ExpenseController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    private const LIST_WITH = ['category:id,name,kind', 'bankAccount.bank:id,code,name,type,logo', 'recorder:id,name', 'approver:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasContextPermission('finance.books.view'), 403);

        $query = $this->baseQuery($request)->with(self::LIST_WITH);

        if ($this->activeBranchOrNull($request) === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['expense_date', 'amount', 'status', 'created_at'], 'expense_date');

        return ExpenseResource::collection($query->paginate($this->perPage($request)));
    }

    /** Tiles above the register: approved total, pending queue, this month. */
    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('finance.books.view'), 403);

        $row = $this->baseQuery($request)->toBase()->selectRaw(
            <<<'SQL'
            COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS approved_total,
            COUNT(*) FILTER (WHERE status = 'pending') AS pending_count,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
            COUNT(*) FILTER (WHERE status = 'approved') AS approved_count,
            COUNT(*) FILTER (WHERE status = 'rejected') AS rejected_count,
            COALESCE(SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END), 0) AS rejected_total
            SQL,
        )->first();

        return response()->json([
            'data' => [
                'approved_total' => number_format((float) ($row->approved_total ?? 0), 2, '.', ''),
                'approved_count' => (int) ($row->approved_count ?? 0),
                'pending_total' => number_format((float) ($row->pending_total ?? 0), 2, '.', ''),
                'pending_count' => (int) ($row->pending_count ?? 0),
                'rejected_total' => number_format((float) ($row->rejected_total ?? 0), 2, '.', ''),
                'rejected_count' => (int) ($row->rejected_count ?? 0),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('finance.books.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $this->validatePayload($request, $branch->school_id, $branch->id);

        $expense = Expense::create([
            ...$data,
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'status' => 'pending',
            'recorded_by' => $request->user()->id,
        ]);

        // Four-eyes: the approvers learn a decision awaits (never the recorder).
        app(Notifier::class)->toStaff($branch->school_id, $branch->id, 'finance.books.approve', 'finance.expense_submitted', [
            'title' => $expense->title,
            'amount' => number_format((float) $expense->amount, 2),
        ], [
            'link' => '/finance?tab=expenses',
            'exceptUserId' => $request->user()->id,
        ]);

        return (new ExpenseResource($expense->load(self::LIST_WITH)))
            ->additional(['message' => 'Expense recorded — pending approval.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Expense $expense): ExpenseResource
    {
        $this->authorizeRow($request, $expense, 'finance.books.manage');
        $this->assertPending($expense);

        $expense->update($this->validatePayload($request, $expense->school_id, $expense->branch_id));

        return (new ExpenseResource($expense->load(self::LIST_WITH)))
            ->additional(['message' => 'Expense saved.']);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeRow($request, $expense, 'finance.books.manage');
        $this->assertPending($expense);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    public function approve(Request $request, Expense $expense): ExpenseResource
    {
        return $this->decide($request, $expense, approved: true);
    }

    public function reject(Request $request, Expense $expense): ExpenseResource
    {
        return $this->decide($request, $expense, approved: false);
    }

    private function decide(Request $request, Expense $expense, bool $approved): ExpenseResource
    {
        $this->authorizeRow($request, $expense, 'finance.books.approve');
        $this->assertPending($expense);

        if ($this->isSelfApproval($request, $expense)) {
            throw ValidationException::withMessages([
                'expense' => ['You recorded this expense — a different approver must decide it.'],
            ]);
        }

        $data = $request->validate([
            'review_note' => [$approved ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        $this->applyDecision($expense, $approved, $data['review_note'] ?? null, $request);

        return (new ExpenseResource($expense->load(self::LIST_WITH)))
            ->additional(['message' => $approved ? 'Expense approved.' : 'Expense rejected.']);
    }

    /**
     * Countersign a batch of pending expenses. Every row is checked in its own
     * branch and against the four-eyes rule individually — a sweep must never
     * become the loophole that lets one person record and approve their own
     * spending, which is the whole point of `finance_self_approval`.
     */
    public function bulkDecide(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            // Rejections must say why, exactly as on the single-row endpoint.
            'review_note' => [Rule::requiredIf($request->input('decision') === 'rejected'), 'nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        $approved = $data['decision'] === 'approved';
        $decided = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], Expense::with(self::LIST_WITH), $skipped);

        foreach ($rows as $expense) {
            if (! $actor->hasPermissionForScope('finance.books.approve', $expense->school_id, $expense->branch_id)) {
                $skipped[] = self::skipRow($expense, $expense->title, 'not_permitted');

                continue;
            }

            if ($expense->status !== 'pending') {
                $skipped[] = self::skipRow($expense, $expense->title, 'already_decided');

                continue;
            }

            if ($this->isSelfApproval($request, $expense)) {
                $skipped[] = self::skipRow($expense, $expense->title, 'self_approval');

                continue;
            }

            $this->applyDecision($expense, $approved, $data['review_note'] ?? null, $request);
            $decided++;
        }

        return response()->json([
            'message' => "{$decided} expense(s) decided.",
            'meta' => ['decided' => $decided, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * The four-eyes rule: the recorder never countersigns their own expense —
     * unless the school explicitly allows one-person finance
     * (`finance_self_approval` school setting, off by default).
     */
    private function isSelfApproval(Request $request, Expense $expense): bool
    {
        return $expense->recorded_by === $request->user()->id
            && ! (School::query()->whereKey($expense->school_id)->first(['id', 'settings'])
                ?->financeSelfApprovalAllowed() ?? false);
    }

    /** The decision write + notification, shared by the single and bulk paths. */
    private function applyDecision(Expense $expense, bool $approved, ?string $note, Request $request): void
    {
        $expense->update([
            'status' => $approved ? 'approved' : 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'review_note' => $note,
        ]);

        app(Notifier::class)->toUser($expense->recorder, 'finance.expense_decided', [
            'title' => $expense->title,
            'amount' => number_format((float) $expense->amount, 2),
            'status' => $approved ? 'approved' : 'rejected',
        ], [
            'link' => '/finance?tab=expenses',
            'schoolId' => $expense->school_id,
            'branchId' => $expense->branch_id,
            'exceptUserId' => $request->user()->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, int $schoolId, int $branchId): array
    {
        $data = $request->validate([
            'finance_category_id' => [
                'required',
                Rule::exists('finance_categories', 'id')
                    ->where('school_id', $schoolId)
                    ->where('kind', 'expense'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            // You record money already spent — never a future spend (that's a
            // budget line, not an expense). Addis wall clock, like attendance.
            'expense_date' => ['required', 'date', 'after:2000-01-01', 'before_or_equal:'.Ethiopia::today()],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'wallet', 'other'])],
            'bank_account_id' => ['nullable', 'integer'],
            'payee' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'expense_date.before_or_equal' => __('dates.day_future'),
        ]);

        // Bank/wallet spends may name the account the money left; it must be
        // one of the school's accounts enabled for this branch.
        if (! in_array($data['method'], ['bank_transfer', 'wallet'], true)) {
            $data['bank_account_id'] = null;
        } elseif (($data['bank_account_id'] ?? null) !== null) {
            $usable = BankAccount::query()
                ->whereKey($data['bank_account_id'])
                ->usableByBranch($branchId)
                ->exists();

            if (! $usable) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Pick a bank account that is active for this branch.'],
                ]);
            }
        }

        return $data;
    }

    private function authorizeRow(Request $request, Expense $expense, string $permission): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope($permission, $expense->school_id, $expense->branch_id),
            403,
        );
    }

    private function assertPending(Expense $expense): void
    {
        if ($expense->status !== 'pending') {
            throw ValidationException::withMessages([
                'expense' => ['Only pending expenses can be changed — approved history is immutable.'],
            ]);
        }
    }

    /**
     * @return Builder<Expense>
     */
    private function baseQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        return Expense::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($this->csvValues($request, 'status'), fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->when($this->csvIds($request, 'finance_category_id'), fn ($q, array $ids) => $q->whereIn('finance_category_id', $ids))
            ->when($this->csvValues($request, 'method'), fn ($q, array $methods) => $q->whereIn('method', $methods))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('expense_date', '<=', $to))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->where('title', 'ilike', $this->needle($n))
                ->orWhere('payee', 'ilike', $this->needle($n))
                ->orWhere('reference', 'ilike', $this->needle($n))));
    }
}
