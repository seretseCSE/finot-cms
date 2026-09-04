<?php

namespace App\Http\Resources;

use App\Models\AssessmentResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssessmentResult */
class AssessmentResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'student_id' => $this->student_id,
            'score' => $this->score,
            'is_absent' => $this->is_absent,
            'remarks' => $this->remarks,
            'recorded_by' => $this->recorded_by,
            'updated_at' => $this->updated_at,
        ];
    }
}
