<?php

namespace App\Http\Resources;

use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssignmentSubmission */
class AssignmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->full_name),
            'student_public_id' => $this->whenLoaded('student', fn () => $this->student?->public_id),
            'student_photo_url' => $this->whenLoaded('student', fn () => $this->student?->photo_url),
            'body' => $this->presentBody(),
            'files' => collect($this->files ?? [])->map(fn (array $file): array => [
                'name' => $file['name'] ?? 'file',
                'size' => $file['size'] ?? null,
                'mime_type' => $file['mime_type'] ?? null,
                'url' => isset($file['path']) ? s3Url($file['path']) : null,
            ])->all(),
            'link_url' => $this->link_url,
            'attempt_count' => $this->attempt_count,
            'submitted_at' => $this->submitted_at,
            'is_late' => $this->is_late,
            'status' => $this->status->value,
            'score' => $this->score !== null ? (float) $this->score : null,
            'rubric_scores' => $this->rubric_scores,
            'feedback' => $this->presentFeedback(),
            'graded_at' => $this->graded_at,
            'graded_by_name' => $this->whenLoaded('grader', fn () => $this->grader?->name),
        ];
    }
}
