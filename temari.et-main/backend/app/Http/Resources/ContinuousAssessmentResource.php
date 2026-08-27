<?php

namespace App\Http\Resources;

use App\Models\ContinuousAssessment;
use App\Models\ContinuousAssessmentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContinuousAssessment */
class ContinuousAssessmentResource extends JsonResource
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
            'term_id' => $this->term_id,
            'term_name' => $this->whenLoaded('term', fn () => $this->term?->name),
            'name' => $this->name,
            'targets' => $this->whenLoaded('targets', fn () => $this->presented_targets ?? []),
            'is_active' => $this->is_active,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'total_weight' => $this->whenLoaded('items', fn () => round((float) $this->items->sum('weight'), 2)),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (ContinuousAssessmentItem $item): array => [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'weight' => (float) $item->weight,
                'max_score' => (float) $item->max_score,
                'due_on' => $item->due_on?->toDateString(),
                'sort_order' => $item->sort_order,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
