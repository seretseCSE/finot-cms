<?php

namespace App\Http\Resources;

use App\Models\HealthCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HealthCondition */
class HealthConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'is_active' => $this->is_active,
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at,
        ];
    }
}
