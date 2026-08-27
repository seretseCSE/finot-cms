<?php

namespace App\Http\Resources;

use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentEnrollment
 */
class StudentEnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            // Which school/branch this enrollment belongs to — the history
            // timeline spans schools (transfers), so every row names its own.
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'school_name' => $this->when(
                $this->relationLoaded('branch') && ($this->branch?->relationLoaded('school') ?? false),
                fn () => $this->branch?->school?->name,
            ),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->whenLoaded('academicYear', fn () => $this->academicYear->name),
            'section_id' => $this->section_id,
            'section_name' => $this->whenLoaded('section', fn () => $this->section?->name),
            // The homeroom teacher for THIS enrollment's year (homerooms are
            // year-scoped) — present only when section.homerooms was loaded.
            'homeroom_teacher' => $this->when(
                $this->relationLoaded('section') && ($this->section?->relationLoaded('homerooms') ?? false),
                function (): ?array {
                    $employee = $this->section->homerooms
                        ->firstWhere('academic_year_id', $this->academic_year_id)
                        ?->employee;

                    return $employee === null ? null : [
                        'employee_id' => $employee->id,
                        // The linked account — lets the profile open a staff
                        // direct chat with the homeroom teacher.
                        'user_id' => $employee->user_id,
                        'name' => $employee->full_name,
                        'phone' => $employee->phone,
                    ];
                },
            ),
            'grade_level_id' => $this->grade_level_id,
            'grade_level' => new GradeLevelResource($this->whenLoaded('gradeLevel')),
            'school_program_id' => $this->school_program_id,
            'school_program_name' => $this->whenLoaded('schoolProgram', fn () => $this->schoolProgram?->name),
            'previous_school_id' => $this->previous_school_id,
            'previous_school_name' => $this->whenLoaded('previousSchool', fn () => $this->previousSchool?->name),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'enrolled_on' => $this->enrolled_on?->toDateString(),
            'exited_on' => $this->exited_on?->toDateString(),
        ];
    }
}
