<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\DocumentNotifier;
use App\Services\Documents\DocumentService;
use App\Services\EnrollmentGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment against an invoice and keeps its amount_paid and status
 * in sync. Rejects payments on void invoices or that exceed the balance.
 * Temari takes NO cut of school fee payments — schools keep 100% of what
 * families pay; platform revenue comes from subscriptions, never fees.
 */
class RecordPaymentAction
{
    /**
     * @param  array{amount: float|string, method: string, reference?: ?string, paid_at?: ?string, note?: ?string}  $data
     */
    public function execute(Invoice $invoice, array $data, ?int $recordedBy): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $recordedBy): Payment {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    'amount' => ['This invoice has been voided.'],
                ]);
            }

            if ($invoice->status === InvoiceStatus::Scholarship) {
                throw ValidationException::withMessages([
                    'amount' => ['This invoice is fully covered by a scholarship — there is nothing to pay.'],
                ]);
            }

            $amount = round((float) $data['amount'], 2);
            // Balance judges against the NET (post-discount/scholarship)
            // amount plus any accrued late penalty.
            $balance = round($invoice->totalDue() - (float) $invoice->amount_paid, 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['The amount must be greater than zero.'],
                ]);
            }

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => ["The amount exceeds the outstanding balance ({$balance})."],
                ]);
            }

            // Official receipt number from the branch's monotonic counter,
            // allocated under row lock so concurrent cashiers never collide.
            $branch = Branch::query()->lockForUpdate()->findOrFail($invoice->branch_id);
            $branch->increment('receipt_counter');
            $receiptNumber = sprintf('RCT-%s-%06d', $branch->code, $branch->receipt_counter);

            $payment = $invoice->payments()->create([
                'school_id' => $invoice->school_id,
                'branch_id' => $invoice->branch_id,
                'student_id' => $invoice->student_id,
                'amount' => $amount,
                'method' => $data['method'],
                // Snapshot the collection account at payment time — re-pointing
                // the fee later must never rewrite where past money landed.
                // With multiple fee accounts, only an explicit override is used
                // (or the sole attached account when there is exactly one).
                // Cash/other never land in an account, so no fallback there.
                'bank_account_id' => $data['bank_account_id']
                    ?? ((PaymentMethod::tryFrom($data['method'])?->needsAccount() ?? false)
                        ? $invoice->feeStructure?->defaultBankAccountId()
                        : null),
                'reference' => $data['reference'] ?? null,
                'receipt_number' => $receiptNumber,
                // Unguessable token behind the receipt's public QR
                // verification page — knowing a receipt number is never
                // enough to read one.
                'receipt_token' => Str::random(40),
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'recorded_by' => $recordedBy,
                'note' => $data['note'] ?? null,
            ]);

            $paid = round((float) $invoice->amount_paid + $amount, 2);
            $invoice->amount_paid = $paid;
            $invoice->status = $paid >= $invoice->totalDue()
                ? InvoiceStatus::Paid
                : InvoiceStatus::Partial;
            $invoice->save();

            // A settled registration fee lifts the enrollment gate.
            app(EnrollmentGate::class)->onInvoiceSettled($invoice);

            // Once safely committed: pre-warm the official PDF receipt (so the
            // download is instant later) and text/email the family the receipt
            // link. Neither may ever undo a recorded payment.
            DB::afterCommit(function () use ($payment): void {
                try {
                    app(DocumentService::class)->ensure('payment_receipt', $payment);
                } catch (\Throwable $e) {
                    Log::warning('Receipt PDF pre-warm failed.', [
                        'payment_id' => $payment->id, 'error' => $e->getMessage(),
                    ]);
                }

                app(DocumentNotifier::class)->paymentReceived($payment);
            });

            return $payment;
        });
    }
}
