<?php

namespace App\Http\Resources;

use App\Models\StockTakeLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTakeLine
 */
class StockTakeLineResource extends JsonResource
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
            'expected_quantity' => $this->expected_quantity,
            'counted_quantity' => $this->counted_quantity,
        ];
    }
}
