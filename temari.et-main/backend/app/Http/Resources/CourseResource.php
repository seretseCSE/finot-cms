<?php

namespace App\Http\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Staff shape for courses. */
/** @mixin Course */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->presentDescription(),
            'is_platform' => $this->isPlatform(),
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'subject_assignment_id' => $this->subject_assignment_id,
            'section_name' => $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->section?->name),
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($t): array => [
                'subject_assignment_id' => $t->subject_assignment_id,
                'section_name' => $t->subjectAssignment?->section?->name,
            ])),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'min_grade_sort' => $this->min_grade_sort,
            'max_grade_sort' => $this->max_grade_sort,
            'stream' => $this->stream,
            'language' => $this->language,
            'cover_url' => $this->cover_path !== null ? s3Url($this->cover_path) : null,
            'is_sequential' => $this->is_sequential,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'modules_count' => $this->whenCounted('modules'),
            'lessons_count' => $this->whenCounted('lessons'),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
