<?php

namespace App\Http\Resources;

use App\Models\InventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryCategory
 */
class InventoryCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'is_platform' => $this->school_id === null,
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
