<?php

namespace App\Http\Resources;

use App\Models\StudentTermResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentTermResult
 */
class StudentTermResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student_enrollment_id' => $this->student_enrollment_id,
            'term_id' => $this->term_id,
            'term' => $this->whenLoaded('term', fn (): array => [
                'id' => $this->term->id,
                'name' => $this->term->name,
                'status' => $this->term->status->value,
            ]),
            'section_id' => $this->section_id,
            'section_name' => $this->whenLoaded('section', fn () => $this->section?->name),
            'grade_level_id' => $this->grade_level_id,
            'grade_level_name' => $this->whenLoaded('section', fn () => $this->section?->gradeLevel?->name),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'public_id' => $this->student->public_id,
                'full_name' => $this->student->full_name,
                'gender' => $this->student->gender,
                'photo_url' => $this->student->photo_url,
            ]),
            'total' => $this->total !== null ? (float) $this->total : null,
            'average' => $this->average !== null ? (float) $this->average : null,
            'rank' => $this->rank,
            'rank_of' => $this->rank_of,
            'subject_count' => $this->subject_count,
            'breakdown' => $this->breakdown,
            'grading' => $this->grading,
            'conduct' => $this->conduct,
            'skills' => $this->skills,
            'absence_days' => $this->absence_days,
            'comment' => $this->comment,
            'computed_at' => $this->computed_at?->toISOString(),
        ];
    }
}
