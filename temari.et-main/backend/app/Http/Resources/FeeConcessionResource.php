<?php

namespace App\Http\Resources;

use App\Models\FeeConcession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeConcession
 */
class FeeConcessionResource extends JsonResource
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
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->full_name),
            'student_public_id' => $this->whenLoaded('student', fn () => $this->student?->public_id),
            'parent_id' => $this->parent_id,
            'parent_name' => $this->whenLoaded('parentProfile', function (): ?string {
                $profile = $this->parentProfile;
                $trio = trim(implode(' ', array_filter([
                    $profile?->first_name, $profile?->father_name, $profile?->grandfather_name,
                ])));

                return $trio !== '' ? $trio : $profile?->user?->name;
            }),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'fee_types' => $this->fee_types,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->whenLoaded('academicYear', fn () => $this->academicYear?->name),
            'term_id' => $this->term_id,
            'term_name' => $this->whenLoaded('term', fn () => $this->term?->name),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source,
            'reason' => $this->reason,
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at,
            'revoked_at' => $this->revoked_at,
            // How many bills this concession has actually touched.
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => $this->created_at,
        ];
    }
}
