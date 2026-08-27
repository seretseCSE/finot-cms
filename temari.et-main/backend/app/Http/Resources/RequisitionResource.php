<?php

namespace App\Http\Resources;

use App\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Requisition
 */
class RequisitionResource extends JsonResource
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
            'status' => $this->status,
            // The no-self-approval rule needs the requester id client-side —
            // the UI hides Approve on your own rows before the server refuses.
            'requested_by' => $this->requested_by,
            'requested_by_name' => $this->whenLoaded('requester', fn () => $this->requester?->name),
            'purpose' => $this->purpose,
            'decided_by_name' => $this->whenLoaded('decider', fn () => $this->decider?->name),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'items_count' => $this->whenCounted('items'),
            'items' => RequisitionItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
