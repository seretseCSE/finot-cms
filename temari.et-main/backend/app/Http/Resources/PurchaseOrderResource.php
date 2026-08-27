<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
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
            'supplier_name' => $this->supplier_name,
            'supplier_phone' => $this->supplier_phone,
            'status' => $this->status,
            'expected_on' => $this->expected_on?->toDateString(),
            'note' => $this->note,
            'total_cost' => $this->total_cost,
            'ordered_by' => $this->ordered_by,
            'ordered_by_name' => $this->whenLoaded('orderer', fn () => $this->orderer?->name),
            'decided_by_name' => $this->whenLoaded('decider', fn () => $this->decider?->name),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
            'items_count' => $this->whenCounted('items'),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
