<?php

namespace App\Http\Resources;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assignment */
class LmsAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'quiz_id' => $this->quiz_id,
            'quiz_title' => $this->whenLoaded('quiz', fn () => $this->quiz?->title),
            // Quiz-kind completion (the linked quiz's attempts are the real
            // turn-ins) — lets the teacher screen bridge to the exam lane.
            'quiz_stats' => $this->when(
                $this->kind === 'quiz' && $this->relationLoaded('quiz') && $this->quiz !== null,
                fn (): array => [
                    'status' => $this->quiz->status->value,
                    'expected_takers' => (int) ($this->quiz->expected_takers ?? 0),
                    'takers_count' => (int) ($this->quiz->takers_count ?? 0),
                ],
            ),
            'title' => $this->title,
            'instructions' => $this->presentInstructions(),
            'subject_assignment_id' => $this->subject_assignment_id,
            'section_name' => $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->section?->name),
            'grade_level_name' => $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->section?->gradeLevel?->name),
            'subject_name' => $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->subject?->name),
            'submission_types' => $this->submission_types,
            'rubric' => $this->rubric,
            'target_student_ids' => $this->target_student_ids,
            'resubmission_policy' => $this->resubmission_policy,
            // `path` is the staff-lane edit handle (removed_paths) — the /me
            // lane never exposes it.
            'attachments' => collect($this->attachments ?? [])->map(fn (array $file): array => [
                'name' => $file['name'] ?? 'file',
                'path' => $file['path'] ?? null,
                'size' => $file['size'] ?? null,
                'mime_type' => $file['mime_type'] ?? null,
                'url' => isset($file['path']) ? s3Url($file['path']) : null,
            ])->all(),
            'max_score' => $this->max_score !== null ? (float) $this->max_score : null,
            'available_from' => $this->available_from,
            'due_at' => $this->due_at,
            'late_policy' => $this->late_policy,
            'late_penalty_percent' => $this->late_penalty_percent !== null ? (float) $this->late_penalty_percent : null,
            'status' => $this->status->value,
            'published_at' => $this->published_at,
            'assessment_id' => $this->assessment_id,
            'assessment_name' => $this->whenLoaded('assessment', fn () => $this->assessment?->name),
            'submissions_count' => $this->whenCounted('submissions'),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
        ];
    }
}
