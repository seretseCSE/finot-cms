<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeStructure
 */
class FeeStructureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->whenLoaded('academicYear', fn () => $this->academicYear?->name),
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'bank_accounts' => $this->whenLoaded('bankAccounts', fn () => $this->bankAccounts->map(fn ($account) => [
                'id' => $account->id,
                'account_name' => $account->account_name,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank?->name,
                'bank_code' => $account->bank?->code,
                'bank_logo' => $account->bank?->logo,
                'bank_type' => $account->bank?->type,
            ])->values()),
            // Applicable grades — empty means every grade.
            'grade_levels' => GradeLevelResource::collection($this->whenLoaded('gradeLevels')),
            'starts_on' => $this->starts_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'billing_day' => $this->billing_day,
            'auto_generate' => $this->auto_generate,
            'notify_parents' => $this->notify_parents,
            'notify_students' => $this->notify_students,
            'penalty_type' => $this->penalty_type,
            'penalty_amount' => $this->penalty_amount,
            'penalty_increment_days' => $this->penalty_increment_days,
            'is_active' => $this->is_active,
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => $this->created_at,
        ];
    }
}
