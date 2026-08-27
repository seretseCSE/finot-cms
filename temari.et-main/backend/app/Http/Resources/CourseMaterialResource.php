<?php

namespace App\Http\Resources;

use App\Models\CourseMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CourseMaterial */
class CourseMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->presentDescription(),
            'type' => $this->type,
            'content' => $this->publicContent(),
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'min_grade_sort' => $this->min_grade_sort,
            'max_grade_sort' => $this->max_grade_sort,
            'is_pinned' => $this->is_pinned,
            'is_active' => $this->is_active,
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($t): array => [
                'subject_assignment_id' => $t->subject_assignment_id,
                'section_name' => $t->subjectAssignment?->section?->name,
            ])),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }

    /** Content with the private R2 path swapped for a short-lived signed URL. */
    private function publicContent(): array
    {
        $content = $this->content ?? [];

        if ($this->type === 'file') {
            return [
                'name' => $content['name'] ?? $this->title,
                'size' => $content['size'] ?? null,
                'mime_type' => $content['mime_type'] ?? null,
                'url' => isset($content['path']) ? s3Url($content['path']) : null,
            ];
        }

        if ($this->type === 'text') {
            return ['body' => $this->presentTextBody()];
        }

        return $content;
    }
}
