<?php

namespace App\Http\Resources;

use App\Models\RequisitionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequisitionItem
 */
class RequisitionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->name),
            'item_unit' => $this->whenLoaded('item', fn () => $this->item?->unit),
            'quantity_requested' => $this->quantity_requested,
            'quantity_approved' => $this->quantity_approved,
            'quantity_issued' => $this->quantity_issued,
        ];
    }
}
