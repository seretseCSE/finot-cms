<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'finance_category_id' => $this->finance_category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'title' => $this->title,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date?->toDateString(),
            'method' => $this->method,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->when(
                $this->relationLoaded('bankAccount') && $this->bankAccount !== null,
                fn () => [
                    'id' => $this->bankAccount->id,
                    'account_name' => $this->bankAccount->account_name,
                    'account_number' => $this->bankAccount->account_number,
                    'bank_name' => $this->bankAccount->bank?->name,
                    'bank_logo' => $this->bankAccount->bank?->logo,
                ],
            ),
            'payee' => $this->payee,
            'reference' => $this->reference,
            'note' => $this->note,
            'status' => $this->status,
            // The four-eyes rule needs the recorder's id client-side too —
            // the UI hides Approve on your own rows before the server refuses.
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
        ];
    }
}
