<?php

namespace App\Http\Resources;

use App\Models\Marklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Marklist */
class MarklistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_assignment_id' => $this->subject_assignment_id,
            'status' => $this->status->value,
            'is_locked' => $this->isLocked(),
            'submitted_at' => $this->submitted_at,
            'submitted_by_name' => $this->whenLoaded('submitter', fn () => $this->submitter?->name),
            'approved_at' => $this->approved_at,
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'remarks' => $this->remarks,
            'assisted_by' => $this->assisted_by,
            'assisted_at' => $this->assisted_at,
            'assisted_by_name' => $this->whenLoaded('assister', fn () => $this->assister?->name),
            'assist_reason' => $this->assist_reason,
        ];
    }
}
