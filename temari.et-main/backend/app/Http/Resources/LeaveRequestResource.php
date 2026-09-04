<?php

namespace App\Http\Resources;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'leave_type_id' => $this->leave_type_id,
            'leave_type_name' => $this->whenLoaded('leaveType', fn () => $this->leaveType?->name),
            'leave_type_code' => $this->whenLoaded('leaveType', fn () => $this->leaveType?->code),
            'is_paid' => $this->whenLoaded('leaveType', fn () => $this->leaveType?->is_paid),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => $this->days,
            'is_half_day' => $this->is_half_day,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'requested_by_name' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->name),
            'decided_by_name' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy?->name),
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'created_at' => $this->created_at,
        ];
    }
}
