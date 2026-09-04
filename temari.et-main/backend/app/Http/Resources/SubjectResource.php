<?php

namespace App\Http\Resources;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subject */
class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'category' => $this->category,
            // The explicit grade set (empty = taught in every grade).
            'grade_level_ids' => $this->whenLoaded(
                'gradeLevels',
                fn () => $this->gradeLevels->pluck('id')->values(),
            ),
            'grade_sorts' => $this->whenLoaded(
                'gradeLevels',
                fn () => $this->gradeLevels->pluck('sort_order')->map(intval(...))->sort()->values(),
            ),
            'weight' => $this->weight,
            'room_type' => $this->room_type,
            'is_active' => $this->is_active,
            'assignments_count' => $this->whenCounted('assignments'),
            'created_at' => $this->created_at,
        ];
    }
}
