<?php

namespace App\Http\Resources;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Term
 */
class TermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = now()->toDateString();

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->whenLoaded('academicYear', fn () => $this->academicYear?->name),
            'name' => $this->name,
            'sequence' => $this->sequence,
            'program' => $this->whenLoaded('program', fn () => $this->program ? [
                'id' => $this->program->id,
                'type' => $this->program->type,
                'name' => $this->program->name,
            ] : null),
            // Flat copy for client-side table filtering.
            'program_type' => $this->whenLoaded('program', fn () => $this->program?->type),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'class_starts_at' => $this->class_starts_at ? substr((string) $this->class_starts_at, 0, 5) : null,
            'class_ends_at' => $this->class_ends_at ? substr((string) $this->class_ends_at, 0, 5) : null,
            'period_minutes' => $this->period_minutes,
            'is_quarter' => $this->is_quarter,
            'semester' => $this->semester,
            'is_current' => $this->is_current,
            'status' => $this->status->value,
            // Calendar-derived: today falls inside the term's date range.
            'in_progress' => $this->starts_on !== null
                && $this->ends_on !== null
                && $this->starts_on->toDateString() <= $today
                && $this->ends_on->toDateString() >= $today,
            'is_active' => $this->is_active,
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
        ];
    }
}
