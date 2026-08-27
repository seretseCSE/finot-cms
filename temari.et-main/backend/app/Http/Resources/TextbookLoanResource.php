<?php

namespace App\Http\Resources;

use App\Models\TextbookLoan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TextbookLoan
 */
class TextbookLoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'academic_year_id' => $this->academic_year_id,
            'inventory_item_id' => $this->inventory_item_id,
            'item_name' => $this->whenLoaded('item', fn () => $this->item?->name),
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->full_name),
            'student_public_id' => $this->whenLoaded('student', fn () => $this->student?->public_id),
            'section_id' => $this->section_id,
            'section_name' => $this->whenLoaded('section', fn () => $this->section?->name),
            'quantity' => $this->quantity,
            'status' => $this->status,
            'returned_at' => $this->returned_at?->toIso8601String(),
            'lost_at' => $this->lost_at?->toIso8601String(),
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
