<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'received_quantity' => $this->received_quantity,
        ];
    }
}
