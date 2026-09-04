<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payment collection accounts. Accounts belong to the SCHOOL; the branch pivot
 * (with its own is_active) decides which branches use each account, so one
 * account can be shared or branch-specific. `banks` is the static catalog.
 * Gated by fees.manage — whoever manages fees manages where money lands.
 */
class BankAccountController extends Controller
{
    use HandlesListQueries;

    /** The static bank/wallet catalog (platform-seeded, logos included). */
    public function banks(): JsonResponse
    {
        $banks = Bank::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'logo']);

        return response()->json(['data' => $banks]);
    }

    /**
     * The school's accounts. In a branch context each account carries that
     * branch's attachment state (attached / branch_active); `usable=1` narrows
     * to accounts the branch can take payments on right now.
     */
    public function index(Request $request): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless(
            $request->user()->hasPermissionForScope('fees.view', $schoolId, $branch?->id),
            403,
        );

        abort_if($schoolId === null, 422, 'Select a school context to manage accounts.');

        // School-wide callers may name a branch to judge attachment/usability
        // against (fee forms picking collection accounts for a target branch).
        if ($branch === null && ($filterId = $this->branchFilterId($request, null)) !== null) {
            $branch = Branch::where('school_id', $schoolId)->find($filterId);
        }

        $accounts = BankAccount::query()
            ->where('school_id', $schoolId)
            ->with(['bank:id,code,name,type,logo', 'branches:id,name'])
            // Collection vitals as indexed subselects — the page stays 1 query.
            ->withCount(['payments as payments_count', 'feeStructures as fee_structures_count'])
            ->withSum('payments as collected_sum', 'amount')
            ->withMax('payments as last_payment_at', 'paid_at')
            ->when($branch !== null && $request->boolean('usable'), fn ($q) => $q->usableByBranch($branch->id))
            ->orderBy('account_name')
            ->get();

        return response()->json([
            'data' => $accounts->map(fn (BankAccount $account) => $this->present($account, $branch)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('fees.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => [
                'required', 'string', 'max:50',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('school_id', $branch->school_id)
                    ->where('bank_id', $request->integer('bank_id'))
                    ->whereNull('deleted_at'),
            ],
            // Extra branches of the school to share the account with.
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('school_id', $branch->school_id)],
        ]);

        $account = BankAccount::create([
            'school_id' => $branch->school_id,
            'bank_id' => $data['bank_id'],
            'account_name' => $data['account_name'],
            'account_number' => $data['account_number'],
            'is_active' => true,
        ]);

        $branchIds = collect($data['branch_ids'] ?? [])->push($branch->id)->unique()->values();
        $account->branches()->sync($branchIds->mapWithKeys(fn (int $id) => [$id => ['is_active' => true]]));

        return response()->json([
            'data' => $this->present($account->load(['bank:id,code,name,type,logo', 'branches:id,name']), $branch),
            'message' => 'Bank account added.',
        ], 201);
    }

    public function update(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);

        abort_unless(
            $bankAccount->school_id === ($branch?->school_id ?? $this->activeSchoolScopeId($request))
            && $request->user()->hasPermissionForScope('fees.manage', $bankAccount->school_id, $branch?->id),
            403,
        );

        $data = $request->validate([
            'account_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('school_id', $bankAccount->school_id)
                    ->where('bank_id', $bankAccount->bank_id)
                    ->whereNull('deleted_at')
                    ->ignore($bankAccount->id),
            ],
            'is_active' => ['sometimes', 'boolean'], // school-level switch
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('school_id', $bankAccount->school_id)],
            // Toggle THIS branch's attachment without touching others.
            'branch_active' => ['sometimes', 'boolean'],
        ]);

        $bankAccount->fill(collect($data)->only(['account_name', 'account_number', 'is_active'])->all())->save();

        if (array_key_exists('branch_ids', $data)) {
            $current = $bankAccount->branches()->pluck('bank_account_branch.is_active', 'branches.id');
            $sync = collect($data['branch_ids'])->unique()->mapWithKeys(fn (int $id) => [
                $id => ['is_active' => (bool) ($current[$id] ?? true)],
            ]);
            $bankAccount->branches()->sync($sync);
        }

        if (array_key_exists('branch_active', $data) && $branch !== null) {
            $bankAccount->branches()->syncWithoutDetaching([
                $branch->id => ['is_active' => (bool) $data['branch_active']],
            ]);
        }

        return response()->json([
            'data' => $this->present($bankAccount->load(['bank:id,code,name,type,logo', 'branches:id,name']), $branch),
            'message' => 'Bank account updated.',
        ]);
    }

    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);

        abort_unless(
            $bankAccount->school_id === ($branch?->school_id ?? $this->activeSchoolScopeId($request))
            && $request->user()->hasPermissionForScope('fees.manage', $bankAccount->school_id, $branch?->id),
            403,
        );

        // Collected money must stay traceable to its account — deactivate
        // instead of deleting once payments have landed here.
        if ($bankAccount->payments()->exists()) {
            throw ValidationException::withMessages([
                'account' => ['Payments have been collected into this account — deactivate it instead of deleting.'],
            ]);
        }

        $bankAccount->delete();

        return response()->json(['message' => 'Bank account removed.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BankAccount $account, ?Branch $branch): array
    {
        $pivot = $branch?->id !== null
            ? $account->branches->firstWhere('id', $branch->id)?->pivot
            : null;

        return [
            'id' => $account->id,
            'school_id' => $account->school_id,
            'bank' => $account->bank ? [
                'id' => $account->bank->id,
                'code' => $account->bank->code,
                'name' => $account->bank->name,
                'type' => $account->bank->type,
                'logo' => $account->bank->logo,
            ] : null,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'is_active' => $account->is_active,
            'branches' => $account->branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'is_active' => (bool) $b->pivot->is_active,
            ])->values(),
            'attached_to_branch' => $pivot !== null,
            'branch_active' => $pivot !== null ? (bool) $pivot->is_active : null,
            // Collection vitals — present when queried with the stats subselects.
            'payments_count' => $account->hasAttribute('payments_count') ? (int) $account->payments_count : null,
            'collected_sum' => $account->hasAttribute('collected_sum')
                ? number_format((float) ($account->collected_sum ?? 0), 2, '.', '')
                : null,
            'fee_structures_count' => $account->hasAttribute('fee_structures_count') ? (int) $account->fee_structures_count : null,
            'last_payment_at' => $account->hasAttribute('last_payment_at') ? $account->last_payment_at : null,
            'created_at' => $account->created_at,
        ];
    }

    /**
     * Payments that landed in ONE account — the detail page's table. Branch
     * staff see their branch's slice; school-wide staff the whole account.
     */
    public function payments(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);

        abort_unless(
            $bankAccount->school_id === ($branch?->school_id ?? $this->activeSchoolScopeId($request))
            && $request->user()->hasPermissionForScope('fees.view', $bankAccount->school_id, $branch?->id),
            403,
        );

        $payments = Payment::query()
            ->where('bank_account_id', $bankAccount->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->csvValues($request, 'method'), fn ($q, array $methods) => $q->whereIn('method', $methods))
            ->with(['student:id,first_name,father_name,grandfather_name,public_id', 'invoice:id,title,status', 'recorder:id,name'])
            ->latest('paid_at')
            ->latest('id')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => sprintf('INV-%06d', $payment->invoice_id),
                'invoice_title' => $payment->invoice?->title,
                'student_name' => $payment->student?->full_name,
                'student_public_id' => $payment->student?->public_id,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toDateString(),
                'recorded_by_name' => $payment->recorder?->name,
            ])->values(),
            'meta' => [
                'total' => $payments->total(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Collection analytics for ONE account: totals, method mix, a 12-month
     * series and the fees that collected into it. Cached briefly — the detail
     * page is a report, not a live ledger.
     */
    public function stats(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $branch = $this->activeBranchOrNull($request);

        abort_unless(
            $bankAccount->school_id === ($branch?->school_id ?? $this->activeSchoolScopeId($request))
            && $request->user()->hasPermissionForScope('fees.view', $bankAccount->school_id, $branch?->id),
            403,
        );

        $data = cache()->remember(
            sprintf('bank-account-stats:%d:%s', $bankAccount->id, $branch?->id ?? 'school'),
            300,
            function () use ($bankAccount, $branch): array {
                $base = Payment::query()
                    ->where('bank_account_id', $bankAccount->id)
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch->id));

                $totals = (clone $base)->selectRaw(
                    'COALESCE(SUM(amount), 0) AS collected, COUNT(*) AS transactions, MAX(paid_at) AS last_paid_at',
                )->first();

                $byMethod = (clone $base)
                    ->selectRaw('method, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count')
                    ->groupBy('method')
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn ($row): array => [
                        'method' => $row->method->value,
                        'method_label' => $row->method->label(),
                        'total' => (string) round((float) $row->total, 2),
                        'count' => (int) $row->count,
                    ])
                    ->values();

                $monthly = (clone $base)
                    ->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())
                    ->selectRaw("to_char(date_trunc('month', paid_at), 'YYYY-MM') AS month, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count")
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
                    ->map(fn ($row): array => [
                        'month' => $row->month,
                        'total' => (string) round((float) $row->total, 2),
                        'count' => (int) $row->count,
                    ])
                    ->values();

                $byFee = (clone $base)
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->selectRaw('invoices.title AS fee, COALESCE(SUM(payments.amount), 0) AS total, COUNT(*) AS count')
                    ->groupBy('invoices.title')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row): array => [
                        'fee' => $row->fee,
                        'total' => (string) round((float) $row->total, 2),
                        'count' => (int) $row->count,
                    ])
                    ->values();

                return [
                    'collected' => (string) round((float) ($totals->collected ?? 0), 2),
                    'transactions' => (int) ($totals->transactions ?? 0),
                    'last_paid_at' => $totals->last_paid_at,
                    'by_method' => $byMethod,
                    'monthly' => $monthly,
                    'by_fee' => $byFee,
                ];
            },
        );

        return response()->json(['data' => $data]);
    }
}
