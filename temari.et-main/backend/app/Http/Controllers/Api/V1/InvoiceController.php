<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApplyInvoiceDiscountAction;
use App\Actions\RecordPaymentAction;
use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentVerificationStatus;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentVerification;
use App\Models\Student;
use App\Services\FeeConcessionResolver;
use App\Services\Notify\Notifier;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    /** Eager loads a list row needs: names, concession, where money landed. */
    private const LIST_WITH = [
        'student:id,first_name,father_name,grandfather_name,public_id',
        'academicYear:id,name',
        'term:id,name',
        'feeStructure.bankAccounts.bank:id,code,name,type,logo',
        'payments.bankAccount.bank:id,code,name,type,logo',
        'concession:id,category,status',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $query = $this->baseQuery($request)
            ->with(self::LIST_WITH)
            ->withCount($this->pendingVerificationsCount());

        if ($this->activeBranchOrNull($request) === null) {
            $query->with('branch.school');
        }

        $this->applySort($query, $request, ['created_at', 'due_date', 'amount', 'status'], 'created_at');

        return InvoiceResource::collection($query->paginate($this->perPage($request)));
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->baseQuery($request)
            ->with(self::LIST_WITH)
            ->withCount($this->pendingVerificationsCount())
            ->latest()
            ->limit(5000)
            ->get();

        return InvoiceResource::collection($invoices);
    }

    /**
     * Billing vitals for the CURRENT view — same scope and filters as the
     * list, so the tiles always describe what the table shows.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        // Outstanding/overdue judge against net + accrued late penalty.
        $net = Invoice::totalDueSql();

        $row = $this->baseQuery($request)->selectRaw(
            <<<SQL
            COALESCE(SUM(CASE WHEN status != 'void' THEN {$net} ELSE 0 END), 0) AS invoiced,
            COALESCE(SUM(CASE WHEN status != 'void' THEN amount_paid ELSE 0 END), 0) AS collected,
            COALESCE(SUM(CASE WHEN status IN ('unpaid', 'partial') THEN ({$net}) - amount_paid ELSE 0 END), 0) AS outstanding,
            COUNT(*) FILTER (WHERE status IN ('unpaid', 'partial') AND due_date < CURRENT_DATE) AS overdue_count,
            COALESCE(SUM(CASE WHEN status IN ('unpaid', 'partial') AND due_date < CURRENT_DATE THEN ({$net}) - amount_paid ELSE 0 END), 0) AS overdue_amount
            SQL,
        )->first();

        return response()->json([
            'data' => [
                'invoiced' => (string) round((float) ($row->invoiced ?? 0), 2),
                'collected' => (string) round((float) ($row->collected ?? 0), 2),
                'outstanding' => (string) round((float) ($row->outstanding ?? 0), 2),
                'overdue_count' => (int) ($row->overdue_count ?? 0),
                'overdue_amount' => (string) round((float) ($row->overdue_amount ?? 0), 2),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request, FeeConcessionResolver $concessions): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('fees.manage', $branch->school_id, $branch->id),
            403,
        );

        if (! Student::where('id', $request->integer('student_id'))->where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['student_id' => ['The student must belong to this branch.']]);
        }
        if (! AcademicYear::where('id', $request->integer('academic_year_id'))->where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => ['The academic year must belong to this branch.']]);
        }

        // Optionally anchored to a fee — the invoice inherits the fee's
        // collection accounts and concession scope, and must not duplicate a
        // bulk-generated bill (same fee × term × student).
        $fee = null;
        if ($request->filled('fee_structure_id')) {
            $fee = FeeStructure::query()
                ->where('branch_id', $branch->id)
                ->find($request->integer('fee_structure_id'));

            if ($fee === null) {
                throw ValidationException::withMessages(['fee_structure_id' => ['The fee must belong to this branch.']]);
            }
            if ($fee->academic_year_id !== $request->integer('academic_year_id')) {
                throw ValidationException::withMessages(['fee_structure_id' => ['The fee belongs to a different academic year.']]);
            }

            $duplicate = Invoice::query()
                ->where('fee_structure_id', $fee->id)
                ->where('student_id', $request->integer('student_id'))
                ->where('term_id', $request->input('term_id'))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['fee_structure_id' => ['This fee has already been billed to this student.']]);
            }
        }

        $invoice = Invoice::create([
            ...$request->validated(),
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'amount_paid' => 0,
            // Explicit — the DB default never round-trips into the in-memory
            // model, and the resource serializes discount_type right away.
            'discount_type' => DiscountType::None->value,
            'status' => InvoiceStatus::Unpaid->value,
        ]);

        // Fee-anchored invoices resolve the fee's concession scope; pure
        // ad-hoc ones only match all-fee concessions.
        $invoice = $concessions->apply($invoice, $fee?->type->value);

        return (new InvoiceResource($invoice->load(self::LIST_WITH)))
            ->additional(['message' => 'Invoice created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        $invoice->load([...self::LIST_WITH, 'payments.recorder:id,name'])
            ->loadCount($this->pendingVerificationsCount());

        return new InvoiceResource($invoice);
    }

    /**
     * Parent payment submissions awaiting finance review — the list badge
     * that tells staff a claim is sitting on this invoice.
     *
     * @return array<string, \Closure>
     */
    private function pendingVerificationsCount(): array
    {
        return [
            'verifications as pending_verifications_count' => fn ($q) => $q
                ->where('status', PaymentVerificationStatus::NeedsReview->value),
        ];
    }

    /**
     * Apply (or clear) a discount/scholarship on an invoice. A full scholarship is
     * the "scholarship" path — history stays exact, per-fee partials work.
     */
    public function applyDiscount(
        Request $request,
        Invoice $invoice,
        ApplyInvoiceDiscountAction $action,
    ): InvoiceResource {
        abort_unless(
            $request->user()->hasPermissionForScope('fees.manage', $invoice->school_id, $invoice->branch_id),
            403,
        );

        $data = $request->validate([
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'scholarship_reason' => ['nullable', 'string', 'max:255', 'required_if:discount_type,full_scholarship'],
        ]);

        $invoice = $action->execute($invoice, $data);

        return (new InvoiceResource($invoice->load(self::LIST_WITH)))
            ->additional(['message' => 'Invoice updated.']);
    }

    /**
     * Forgive the accrued late penalty on an invoice — and stop the daily
     * accrual from re-adding it. The base amount and discount are untouched;
     * a settled base flips the invoice to Paid.
     */
    public function waivePenalty(Request $request, Invoice $invoice): InvoiceResource
    {
        abort_unless(
            $request->user()->hasPermissionForScope('fees.manage', $invoice->school_id, $invoice->branch_id),
            403,
        );

        $invoice->penalty_amount = 0;
        $invoice->penalty_waived = true;

        if (in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true)
            && (float) $invoice->amount_paid >= $invoice->totalDue()
            && (float) $invoice->amount_paid > 0) {
            $invoice->status = InvoiceStatus::Paid;
        }

        $invoice->save();

        return (new InvoiceResource($invoice->load(self::LIST_WITH)))
            ->additional(['message' => 'Late penalty waived.']);
    }

    /**
     * Parent payment-proof submissions for one invoice — the finance review
     * lane. Each row carries the full story: what the family submitted, what
     * check.et found in bank records (the immutable response snapshot), and
     * fraud signals (the same transaction number claimed elsewhere, or
     * already backing a recorded payment).
     */
    public function verifications(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $verifications = $invoice->verifications()
            ->with(['submitter:id,name,phone', 'reviewer:id,name'])
            ->latest()
            ->get();

        // One query per list: every OTHER claim/payment reusing one of these
        // transaction numbers anywhere in the school (fraud radar).
        $numbers = $verifications->pluck('transaction_number')->filter()->unique()->values();

        $otherClaims = $numbers->isEmpty() ? collect() : PaymentVerification::query()
            ->whereIn('transaction_number', $numbers)
            ->whereNotIn('id', $verifications->pluck('id'))
            ->whereHas('invoice', fn ($q) => $q->where('school_id', $invoice->school_id))
            ->with('invoice:id,student_id')
            ->get()
            ->groupBy('transaction_number');

        $paidReferences = $numbers->isEmpty() ? collect() : Payment::query()
            ->where('school_id', $invoice->school_id)
            ->whereIn('reference', $numbers)
            ->get(['id', 'invoice_id', 'reference'])
            ->groupBy('reference');

        return response()->json([
            'data' => $verifications->map(function (PaymentVerification $verification) use ($otherClaims, $paidReferences, $invoice): array {
                $number = $verification->transaction_number;
                $claims = $number ? ($otherClaims[$number] ?? collect()) : collect();
                $payments = $number ? ($paidReferences[$number] ?? collect()) : collect();
                // A payment this verification itself created is not a duplicate.
                $foreignPayments = $payments->reject(fn ($p) => $p->id === $verification->payment_id);

                return [
                    'id' => $verification->id,
                    'status' => $verification->status->value,
                    'status_label' => $verification->status->label(),
                    'failure_reason' => $verification->failure_reason,
                    'method' => $verification->method,
                    'bank_code' => $verification->bank_code,
                    'transaction_number' => $number,
                    'receipt_url' => $verification->receipt_url,
                    'receipt_file_url' => $verification->receipt_path ? s3Url($verification->receipt_path) : null,
                    'submitted_by' => $verification->submitter?->name,
                    'submitted_by_phone' => $verification->submitter?->phone,
                    'payment_id' => $verification->payment_id,
                    'created_at' => $verification->created_at,
                    // What check.et actually saw in bank records.
                    'evidence' => $verification->evidence(),
                    // Fraud radar for the reviewer.
                    'duplicate_claims' => $claims->count(),
                    'duplicate_other_invoices' => $claims
                        ->pluck('invoice_id')
                        ->filter(fn ($id) => $id !== $invoice->id)
                        ->unique()
                        ->map(fn ($id) => sprintf('INV-%06d', $id))
                        ->values(),
                    'already_paid_with' => $foreignPayments->isNotEmpty(),
                    // Manual review resolution trail.
                    'reviewed_by' => $verification->reviewer?->name,
                    'reviewed_at' => $verification->reviewed_at,
                    'review_note' => $verification->review_note,
                ];
            })->values(),
        ]);
    }

    /**
     * Confirm a parked (needs_review) submission after eyeballing the
     * evidence: records the payment (defaulting to the bank-verified amount,
     * clamped to the balance) and stamps the resolution onto the claim.
     */
    public function confirmVerification(
        Request $request,
        Invoice $invoice,
        PaymentVerification $verification,
        RecordPaymentAction $action,
    ): JsonResponse {
        $this->authorize('recordPayment', $invoice);
        abort_unless($verification->invoice_id === $invoice->id, 404);

        if ($verification->status !== PaymentVerificationStatus::NeedsReview) {
            throw ValidationException::withMessages([
                'verification' => ['Only submissions awaiting review can be confirmed.'],
            ]);
        }

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999'],
            'bank_account_id' => [
                'nullable', 'integer',
                Rule::exists('bank_accounts', 'id')
                    ->where('school_id', $invoice->school_id)
                    ->whereNull('deleted_at'),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Fraud guard: the same transaction must never settle two bills.
        if ($verification->transaction_number !== null) {
            $reused = Payment::query()
                ->where('school_id', $invoice->school_id)
                ->where('reference', $verification->transaction_number)
                ->exists();

            if ($reused) {
                throw ValidationException::withMessages([
                    'verification' => ['This transaction number already backs a recorded payment — do not confirm it twice.'],
                ]);
            }
        }

        $balance = round($invoice->totalDue() - (float) $invoice->amount_paid, 2);
        $evidenceAmount = $verification->evidence()['amount'];
        $amount = isset($data['amount'])
            ? (float) $data['amount']
            // The bank-verified amount when readable, clamped to the balance
            // (over-payments park as review precisely for this decision).
            : min(is_numeric($evidenceAmount) ? (float) $evidenceAmount : $balance, $balance);

        $payment = $action->execute($invoice, [
            'amount' => $amount,
            'method' => match ($verification->bank_code) {
                'telebirr', 'cbebirr', 'mpesa' => 'wallet',
                null => 'other',
                default => 'bank_transfer',
            },
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'reference' => $verification->transaction_number,
            'note' => $data['note'] ?? 'Confirmed after manual review of the family\'s submission.',
        ], $request->user()->id);

        $verification->update([
            'status' => PaymentVerificationStatus::Verified->value,
            'failure_reason' => null,
            'payment_id' => $payment->id,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ]);

        // The receipt flow already texts the family; the submitter also gets
        // the explicit "your submission was verified" close of the loop.
        app(Notifier::class)->toUser($verification->submitter, 'finance.payment_verified', [
            'student' => $invoice->student?->full_name ?? '',
            'amount' => number_format($amount, 2),
        ], ['link' => '/me/payments']);

        return response()->json([
            'message' => 'Payment confirmed.',
            'data' => new InvoiceResource($invoice->refresh()->load([...self::LIST_WITH, 'payments.recorder:id,name'])),
        ]);
    }

    /**
     * Reject a parked submission with a reason the family can see — nothing
     * is recorded, the invoice stays open.
     */
    public function rejectVerification(
        Request $request,
        Invoice $invoice,
        PaymentVerification $verification,
    ): JsonResponse {
        $this->authorize('recordPayment', $invoice);
        abort_unless($verification->invoice_id === $invoice->id, 404);

        if ($verification->status !== PaymentVerificationStatus::NeedsReview) {
            throw ValidationException::withMessages([
                'verification' => ['Only submissions awaiting review can be rejected.'],
            ]);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $verification->update([
            'status' => PaymentVerificationStatus::Failed->value,
            'failure_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['reason'],
        ]);

        app(Notifier::class)->toUser($verification->submitter, 'finance.payment_rejected', [
            'student' => $invoice->student?->full_name ?? '',
            'amount' => (string) $invoice->balance,
        ], ['link' => '/me/payments']);

        return response()->json(['message' => 'Submission rejected.']);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice has payments and cannot be deleted.'],
            ]);
        }

        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.']);
    }

    /**
     * Delete a selection of invoices — undoing a mis-generated billing run.
     * An invoice with payments against it is history and is skipped, never
     * silently removed: the money it received has to keep pointing somewhere.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $deleted = 0;
        $skipped = [];

        $rows = $this->bulkRows(
            $data['ids'],
            Invoice::withCount('payments')->with('student:id,first_name,father_name,grandfather_name'),
            $skipped,
        );

        foreach ($rows as $invoice) {
            $label = $invoice->number ?? $invoice->student?->full_name;

            if ($actor->cannot('delete', $invoice)) {
                $skipped[] = self::skipRow($invoice, $label, 'not_permitted');

                continue;
            }

            if (($invoice->payments_count ?? 0) > 0) {
                $skipped[] = self::skipRow($invoice, $label, 'has_payments');

                continue;
            }

            $invoice->delete();
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} invoice(s) deleted.",
            'meta' => ['deleted' => $deleted, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Context scoping + every list filter, shared by index/export/stats.
     *
     * @return Builder<Invoice>
     */
    private function baseQuery(Request $request): Builder
    {
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = Invoice::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($branch === null && $schoolScopeId === null && $request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')));

        $this->applySearch($query, $request, function ($outer, string $n): void {
            $outer->where('title', 'ilike', $this->needle($n))
                ->orWhereHas('student', fn ($s) => $s->where('search_text', 'ilike', $this->needle($n)));

            // "INV-000123" (or bare digits) resolves the invoice number.
            $number = (int) preg_replace('/\D/', '', $n);
            if ($number > 0) {
                $outer->orWhere('invoices.id', $number);
            }
        });

        if ($statuses = $this->csvValues($request, 'status')) {
            $query->whereIn('status', $statuses);
        }

        if ($yearIds = $this->csvIds($request, 'academic_year_id')) {
            $query->whereIn('academic_year_id', $yearIds);
        }

        if ($termIds = $this->csvIds($request, 'term_id')) {
            $query->whereIn('term_id', $termIds);
        }

        if ($feeIds = $this->csvIds($request, 'fee_structure_id')) {
            $query->whereIn('fee_structure_id', $feeIds);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($methods = $this->csvValues($request, 'method')) {
            $query->whereHas('payments', fn ($p) => $p->whereIn('method', $methods));
        }

        // Reconciliation lens: invoices with money received into this account.
        if ($accountIds = $this->csvIds($request, 'bank_account_id')) {
            $query->whereHas('payments', fn ($p) => $p->whereIn('bank_account_id', $accountIds));
        }

        // Invoices with a parent payment submission awaiting review.
        if ($request->boolean('pending_verification')) {
            $query->whereHas('verifications', fn ($v) => $v
                ->where('status', PaymentVerificationStatus::NeedsReview->value));
        }

        // Overdue = past due date and still owing money.
        if ($request->boolean('overdue')) {
            $query->whereDate('due_date', '<', now()->toDateString())
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value]);
        }

        $this->applyDateRange($query, $request, 'created_at', 'issued_from', 'issued_to');
        $this->applyDateRange($query, $request, 'due_date', 'due_from', 'due_to');

        return $query;
    }
}
