<?php

namespace App\Http\Resources;

use App\Models\StockTake;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTake
 */
class StockTakeResource extends JsonResource
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
            'inventory_category_id' => $this->inventory_category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'status' => $this->status,
            'note' => $this->note,
            'started_by_name' => $this->whenLoaded('starter', fn () => $this->starter?->name),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'lines_count' => $this->whenCounted('lines'),
            'counted_count' => $this->whenHas('counted_count'),
            'lines' => StockTakeLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
        ];
    }
}
