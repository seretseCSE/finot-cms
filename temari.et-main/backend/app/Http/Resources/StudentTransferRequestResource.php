<?php

namespace App\Http\Resources;

use App\Models\StudentTransferRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentTransferRequest
 */
class StudentTransferRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'decision_note' => $this->decision_note,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'public_id' => $this->student->public_id,
                'full_name' => $this->student->full_name,
                'gender' => $this->student->gender,
                'photo_url' => $this->student->photo_url,
            ]),
            'from_school_id' => $this->from_school_id,
            'from_school_name' => $this->whenLoaded('fromSchool', fn () => $this->fromSchool->name),
            'from_branch_id' => $this->from_branch_id,
            'from_branch_name' => $this->whenLoaded('fromBranch', fn () => $this->fromBranch->name),
            'to_school_id' => $this->to_school_id,
            'to_school_name' => $this->whenLoaded('toSchool', fn () => $this->toSchool->name),
            'to_branch_id' => $this->to_branch_id,
            'to_branch_name' => $this->whenLoaded('toBranch', fn () => $this->toBranch->name),
            'to_academic_year_id' => $this->to_academic_year_id,
            'to_academic_year_name' => $this->whenLoaded('toAcademicYear', fn () => $this->toAcademicYear->name),
            'to_grade_level_id' => $this->to_grade_level_id,
            'to_grade_level_name' => $this->whenLoaded('toGradeLevel', fn () => $this->toGradeLevel->name),
            'from_enrollment' => $this->whenLoaded('fromEnrollment', fn () => [
                'grade_level_name' => $this->fromEnrollment->gradeLevel?->name,
                'section_name' => $this->fromEnrollment->section?->name,
                'academic_year_name' => $this->fromEnrollment->academicYear?->name,
                'status' => $this->fromEnrollment->status->value,
            ]),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'url' => $a->url(),
                'mime_type' => $a->mime_type,
                'size' => $a->size,
            ])->all()),
            'requested_by_name' => $this->whenLoaded('requester', fn () => $this->requester?->name),
            'decided_by_name' => $this->whenLoaded('decider', fn () => $this->decider?->name),
            'decided_at' => $this->decided_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
