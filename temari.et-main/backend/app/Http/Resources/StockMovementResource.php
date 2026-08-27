<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
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
            'inventory_item_id' => $this->inventory_item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->name),
            'item_unit' => $this->whenLoaded('item', fn () => $this->item?->unit),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'quantity_change' => $this->quantity_change,
            'quantity_after' => $this->quantity_after,
            'unit_cost' => $this->unit_cost,
            'requisition_id' => $this->requisition_id,
            'purchase_order_id' => $this->purchase_order_id,
            'stock_take_id' => $this->stock_take_id,
            'supplier_name' => $this->supplier_name,
            'recipient' => $this->recipient,
            'reference' => $this->reference,
            'note' => $this->note,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
        ];
    }
}
