<?php

namespace App\Actions;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\EnrollmentGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies (or clears) a discount/scholarship on an invoice and recomputes its
 * status against the new net. A full scholarship marks the invoice Scholarship; a
 * partial discount may flip an already-covered invoice to Paid. Refuses when
 * recorded payments already exceed the new net — money must never be orphaned
 * silently (void or refund explicitly instead).
 */
class ApplyInvoiceDiscountAction
{
    /**
     * @param  array{discount_type: string, discount_value?: float|string|null, scholarship_reason?: ?string}  $data
     */
    public function execute(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    'discount_type' => ['This invoice has been voided.'],
                ]);
            }

            $type = DiscountType::from($data['discount_type']);
            $value = round((float) ($data['discount_value'] ?? 0), 2);

            if ($type === DiscountType::Percentage && ($value <= 0 || $value > 100)) {
                throw ValidationException::withMessages([
                    'discount_value' => ['A percentage discount must be between 0 and 100.'],
                ]);
            }

            if ($type === DiscountType::Fixed && ($value <= 0 || $value > (float) $invoice->amount)) {
                throw ValidationException::withMessages([
                    'discount_value' => ['A fixed discount must be between 0 and the invoice amount.'],
                ]);
            }

            $invoice->discount_type = $type;
            $invoice->discount_value = in_array($type, [DiscountType::None, DiscountType::FullScholarship], true) ? 0 : $value;
            $invoice->scholarship_reason = $type === DiscountType::None ? null : ($data['scholarship_reason'] ?? null);

            // A full scholarship forgives any accrued late penalty too — the
            // family owes nothing on this bill.
            if ($type === DiscountType::FullScholarship && (float) $invoice->penalty_amount > 0) {
                $invoice->penalty_amount = 0;
                $invoice->penalty_waived = true;
            }

            $net = $invoice->netAmount();
            $due = $invoice->totalDue();
            $paid = (float) $invoice->amount_paid;

            if ($paid > $due) {
                throw ValidationException::withMessages([
                    'discount_value' => ["Recorded payments ({$paid}) already exceed the discounted amount ({$due}). Void or refund first."],
                ]);
            }

            $invoice->status = match (true) {
                $type === DiscountType::FullScholarship => InvoiceStatus::Scholarship,
                $paid >= $due && $paid > 0 => InvoiceStatus::Paid,
                $net === 0.0 => InvoiceStatus::Scholarship,
                $paid > 0 => InvoiceStatus::Partial,
                default => InvoiceStatus::Unpaid,
            };

            $invoice->save();

            // A scholarship on the registration fee lifts the enrollment gate.
            app(EnrollmentGate::class)->onInvoiceSettled($invoice);

            return $invoice;
        });
    }
}
