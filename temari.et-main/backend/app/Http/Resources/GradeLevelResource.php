<?php

namespace App\Http\Resources;

use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GradeLevel
 */
class GradeLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'cycle' => $this->cycle->value,
            'sort_order' => $this->sort_order,
            'has_national_exam' => $this->has_national_exam,
            'sections_count' => $this->whenCounted('sections'),
            // Branch-scoped lists only: the branch's program ids offering this
            // grade — feeds cascading grade → program pickers.
            'program_ids' => $this->whenHas('program_ids'),
        ];
    }
}
