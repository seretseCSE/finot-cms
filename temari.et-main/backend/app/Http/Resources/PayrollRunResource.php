<?php

namespace App\Http\Resources;

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollRun
 */
class PayrollRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch->name),
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch->school?->name),
            'name' => $this->name,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'gross_total' => $this->gross_total,
            'tax_total' => $this->tax_total,
            'pension_employee_total' => $this->pension_employee_total,
            'pension_employer_total' => $this->pension_employer_total,
            'deduction_total' => $this->deduction_total,
            'net_total' => $this->net_total,
            'employee_count' => $this->whenCounted('items'),
            'approved_at' => $this->approved_at,
            'paid_at' => $this->paid_at,
            'items' => $this->whenLoaded('items', fn () => $this->items
                ->map(fn (PayrollItem $item) => [
                    'id' => $item->id,
                    'employee_id' => $item->employee_id,
                    'employee_name' => $item->employee?->full_name,
                    'basic_salary' => $item->basic_salary,
                    'allowances_total' => $item->allowances_total,
                    'gross_pay' => $item->gross_pay,
                    'income_tax' => $item->income_tax,
                    'pension_employee' => $item->pension_employee,
                    'pension_employer' => $item->pension_employer,
                    'deductions_total' => $item->deductions_total,
                    'net_pay' => $item->net_pay,
                    'breakdown' => $item->breakdown,
                ])
                ->values()),
            'created_at' => $this->created_at,
        ];
    }
}
