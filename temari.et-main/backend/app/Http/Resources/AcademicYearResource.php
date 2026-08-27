<?php

namespace App\Http\Resources;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicYear
 */
class AcademicYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Derived convenience flag: the ACTIVE year is the operating year.
            'is_current' => $this->isCurrent(),
            'is_active' => $this->is_active,
            'terms' => TermResource::collection($this->whenLoaded('terms')),
            'terms_count' => $this->whenCounted('terms'),
            'fees' => FeeStructureResource::collection($this->whenLoaded('fees')),
            'fees_count' => $this->whenCounted('fees'),
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
