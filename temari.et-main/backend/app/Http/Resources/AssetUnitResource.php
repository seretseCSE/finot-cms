<?php

namespace App\Http\Resources;

use App\Models\AssetUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetUnit
 */
class AssetUnitResource extends JsonResource
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
            'tag' => $this->tag,
            'serial_number' => $this->serial_number,
            'condition' => $this->condition,
            'status' => $this->status,
            'acquired_on' => $this->acquired_on?->toDateString(),
            'unit_cost' => $this->unit_cost,
            'note' => $this->note,
            'holder' => $this->when(
                $this->relationLoaded('openAssignment'),
                fn () => $this->openAssignment === null ? null : [
                    'type' => $this->openAssignment->holder_type,
                    'label' => $this->openAssignment->holderLabel(),
                    'since' => $this->openAssignment->assigned_on?->toDateString(),
                    'note' => $this->openAssignment->note,
                ],
            ),
            'created_at' => $this->created_at,
        ];
    }
}
