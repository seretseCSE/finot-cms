<?php

namespace App\Http\Resources;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff shape for quizzes/exams. The access code is never exposed — only
 * whether one is required.
 *
 * @mixin Quiz
 */
class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'instructions' => $this->presentInstructions(),
            'is_platform' => $this->is_platform,
            'subject_assignment_id' => $this->subject_assignment_id,
            'subject_assignment_ids' => $this->whenLoaded('targetAssignments', fn () => $this->targetAssignments->pluck('id')),
            'sections' => $this->whenLoaded('targetAssignments', fn () => $this->targetAssignments
                ->map(fn ($assignment) => ['id' => $assignment->section?->id, 'name' => $assignment->section?->name])
                ->filter(fn (array $section) => $section['id'] !== null)
                ->values()),
            'section_names' => $this->whenLoaded('targetAssignments', fn () => $this->targetAssignments
                ->pluck('section.name')->filter()->values()),
            'section_name' => $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->section?->name),
            'expected_takers' => $this->when(isset($this->expected_takers), fn () => (int) $this->expected_takers),
            'takers_count' => $this->when(isset($this->takers_count), fn () => (int) $this->takers_count),
            'subject_id' => $this->subject_id ?? $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->subject_id),
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject?->name)
                ?? $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->subject?->name),
            'grade_level_id' => $this->grade_level_id,
            'grade_level_name' => $this->whenLoaded('gradeLevel', fn () => $this->gradeLevel?->name)
                ?? $this->whenLoaded('subjectAssignment', fn () => $this->subjectAssignment?->section?->gradeLevel?->name),
            'exam_kind' => $this->exam_kind,
            'exam_year_ec' => $this->exam_year_ec,
            'stream' => $this->stream,
            'language' => $this->language,
            'status' => $this->status?->value,
            'total_points' => (float) $this->total_points,
            'settings' => $this->settings,
            'draw' => $this->draw,
            'parts' => $this->presentParts(),
            'has_access_code' => $this->requiresAccessCode(),
            'question_count' => $this->whenCounted('quizQuestions'),
            'attempts_count' => $this->whenCounted('attempts'),
            'assessment_id' => $this->assessment_id,
            'assessment_name' => $this->whenLoaded('assessment', fn () => $this->assessment?->name),
            'published_at' => $this->published_at,
            'closed_at' => $this->closed_at,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
