<?php

namespace App\Http\Resources;

use App\Models\GradingScale;
use App\Models\GradingScaleBand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradingScale */
class GradingScaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_platform' => $this->isPlatform(),
            'sort_order' => $this->sort_order,
            'bands' => $this->whenLoaded('bands', fn () => $this->bands->map(fn (GradingScaleBand $band): array => [
                'id' => $band->id,
                'min_score' => (float) $band->min_score,
                'max_score' => (float) $band->max_score,
                'letter' => $band->letter,
                'label' => $band->label,
                'grade_points' => $band->grade_points !== null ? (float) $band->grade_points : null,
                'is_passing' => $band->is_passing,
            ])->values()),
            'policies_count' => $this->whenCounted('policies'),
        ];
    }
}
