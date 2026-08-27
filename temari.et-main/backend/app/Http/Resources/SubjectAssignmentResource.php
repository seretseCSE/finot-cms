<?php

namespace App\Http\Resources;

use App\Models\SubjectAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubjectAssignment */
class SubjectAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'subject_id' => $this->subject_id,
            'term_id' => $this->term_id,
            'employee_id' => $this->employee_id,
            'periods_per_week' => $this->periods_per_week,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
        ];
    }
}
