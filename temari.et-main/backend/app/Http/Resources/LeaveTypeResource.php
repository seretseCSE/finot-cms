<?php

namespace App\Http\Resources;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'code' => $this->code,
            'name' => $this->name,
            'days_per_year' => $this->days_per_year,
            'service_bonus_days' => $this->service_bonus_days,
            'service_bonus_every_years' => $this->service_bonus_every_years,
            'is_paid' => $this->is_paid,
            'applicable_gender' => $this->applicable_gender,
            'requires_note' => $this->requires_note,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'requests_count' => $this->whenCounted('requests'),
        ];
    }
}
