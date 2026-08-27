<?php

namespace App\Http\Resources;

use App\Models\GradingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradingPolicy */
class GradingPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'grading_scale_id' => $this->grading_scale_id,
            'scale' => new GradingScaleResource($this->whenLoaded('scale')),
            'display' => $this->display,
            'min_grade_sort' => $this->min_grade_sort,
            'max_grade_sort' => $this->max_grade_sort,
        ];
    }
}
