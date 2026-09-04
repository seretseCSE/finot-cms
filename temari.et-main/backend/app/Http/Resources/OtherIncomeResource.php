<?php

namespace App\Http\Resources;

use App\Models\OtherIncome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OtherIncome
 */
class OtherIncomeResource extends JsonResource
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
            'received_on' => $this->received_on?->toDateString(),
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
            'source' => $this->source,
            'reference' => $this->reference,
            'note' => $this->note,
            'recorded_by_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'created_at' => $this->created_at,
        ];
    }
}
