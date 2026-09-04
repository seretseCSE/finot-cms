<?php

namespace App\Http\Resources;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryItem
 */
class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'inventory_category_id' => $this->inventory_category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'category_icon' => $this->whenLoaded('category', fn () => $this->category?->icon),
            'name' => $this->name,
            'code' => $this->code,
            'unit' => $this->unit,
            'is_asset' => $this->is_asset,
            'reorder_level' => $this->reorder_level,
            'description' => $this->description,
            'is_active' => $this->is_active,
            // Aggregated by the list query (withSum alias) for the active scope.
            'quantity_on_hand' => $this->whenHas('quantity_on_hand', fn () => number_format((float) ($this->quantity_on_hand ?? 0), 2, '.', '')),
        ];
    }
}
