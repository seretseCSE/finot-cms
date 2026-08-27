<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
class SectionResource extends JsonResource
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
            'grade_level_id' => $this->grade_level_id,
            'grade_level' => new GradeLevelResource($this->whenLoaded('gradeLevel')),
            'name' => $this->name,
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            // Year-scoped homeroom: the controller constrains the eager load to
            // the requested (or active) year, so the first row is "the" homeroom.
            'homeroom_employee_id' => $this->whenLoaded('homerooms', fn () => $this->homerooms->first()?->employee_id),
            'homeroom_name' => $this->whenLoaded('homerooms', function () {
                $employee = $this->homerooms->first()?->employee;

                return $employee ? trim("{$employee->first_name} {$employee->father_name}") : null;
            }),
            'homeroom_academic_year_id' => $this->whenLoaded('homerooms', fn () => $this->homerooms->first()?->academic_year_id),
            'is_active' => $this->is_active,
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
