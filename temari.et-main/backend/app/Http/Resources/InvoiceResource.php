<?php

namespace App\Http\Resources;

use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Human-facing invoice number — global search already resolves the
            // bare digits ("1042" finds invoice #1042), so id-derived is enough.
            'number' => sprintf('INV-%06d', $this->id),
            'student_id' => $this->student_id,
            'branch_id' => $this->branch_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->full_name),
            'student_public_id' => $this->whenLoaded('student', fn () => $this->student?->public_id),
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->whenLoaded('academicYear', fn () => $this->academicYear?->name),
            'term_id' => $this->term_id,
            'term_name' => $this->whenLoaded('term', fn () => $this->term?->name),
            'fee_structure_id' => $this->fee_structure_id,
            'title' => $this->title,
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'net_amount' => number_format($this->netAmount(), 2, '.', ''),
            'penalty_amount' => $this->penalty_amount,
            'penalty_waived' => $this->penalty_waived,
            'total_due' => number_format($this->totalDue(), 2, '.', ''),
            // Recurring billing period (Ethiopian month), when this invoice
            // came from the recurring engine.
            'billing_year' => $this->billing_year,
            'billing_month' => $this->billing_month,
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'scholarship_reason' => $this->scholarship_reason,
            'fee_concession_id' => $this->fee_concession_id,
            'concession_category' => $this->whenLoaded('concession', fn () => $this->concession?->category->value),
            'balance' => $this->balance,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            // Parent payment submissions awaiting finance review (list badge).
            'pending_verifications_count' => $this->whenCounted('pending_verifications', fn () => (int) $this->pending_verifications_count),
            // Where the fee expects money to land (payment-sheet default list).
            'collection_accounts' => $this->when(
                $this->relationLoaded('feeStructure') && ($this->feeStructure?->relationLoaded('bankAccounts') ?? false),
                fn () => $this->feeStructure->bankAccounts->map(fn (BankAccount $a): array => self::account($a))->values(),
            ),
            // Where recorded payments actually landed (distinct, table column).
            'paid_accounts' => $this->when(
                $this->relationLoaded('payments'),
                fn () => $this->payments
                    ->map(fn ($p) => $p->relationLoaded('bankAccount') ? $p->bankAccount : null)
                    ->filter()
                    ->unique('id')
                    ->map(fn (BankAccount $a): array => self::account($a))
                    ->values(),
            ),
            'created_at' => $this->created_at,
        ];
    }

    private function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && in_array($this->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function account(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'bank_name' => $account->bank?->name,
            'bank_code' => $account->bank?->code,
            'bank_type' => $account->bank?->type,
            'bank_logo' => $account->bank?->logo,
        ];
    }
}
