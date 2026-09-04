<?php

namespace App\Http\Resources;

use App\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuestionBank */
class QuestionBankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'is_platform' => $this->isPlatform(),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'grade_level_id' => $this->grade_level_id,
            'grade_level_name' => $this->whenLoaded('gradeLevel', fn () => $this->gradeLevel?->name),
            'topics' => $this->topics ?? [],
            'is_active' => $this->is_active,
            'questions_count' => $this->whenCounted('questions'),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
