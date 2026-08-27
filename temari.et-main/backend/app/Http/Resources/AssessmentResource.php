<?php

namespace App\Http\Resources;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assessment */
class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_assignment_id' => $this->subject_assignment_id,
            'type' => $this->type,
            'name' => $this->name,
            'max_score' => $this->max_score,
            'weight' => $this->weight,
            'conducted_on' => $this->conducted_on?->toDateString(),
            'continuous_assessment_item_id' => $this->continuous_assessment_item_id,
            'is_planned' => $this->isPlanned(),
            'results_count' => $this->whenCounted('results'),
            'created_at' => $this->created_at,
        ];
    }
}
