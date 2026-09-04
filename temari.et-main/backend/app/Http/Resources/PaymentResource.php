<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'student_id' => $this->student_id,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'receipt_number' => $this->receipt_number,
            'paid_at' => $this->paid_at?->toDateString(),
            'note' => $this->note,
            // Collection-account snapshot (never rewritten after the fact).
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->when(
                $this->relationLoaded('bankAccount') && $this->bankAccount !== null,
                fn () => [
                    'id' => $this->bankAccount->id,
                    'account_name' => $this->bankAccount->account_name,
                    'account_number' => $this->bankAccount->account_number,
                    'bank_name' => $this->bankAccount->bank?->name,
                    'bank_type' => $this->bankAccount->bank?->type,
                    'bank_logo' => $this->bankAccount->bank?->logo,
                ],
            ),
            'recorded_by_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'created_at' => $this->created_at,
        ];
    }
}
