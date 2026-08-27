<?php

namespace App\Http\Resources;

use App\Models\StudentImportRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentImportRow
 */
class StudentImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            // Named `payload`, NOT `data`: a `data` key inside a resource
            // suppresses Laravel's `data` envelope wrapping.
            'payload' => $this->data,
            'status' => $this->status,
            'issues' => $this->issues ?? [],
            'resolution' => $this->resolution,
            'duplicate_student_id' => $this->duplicate_student_id,
            'duplicate_student' => $this->whenLoaded('duplicateStudent', fn () => [
                'id' => $this->duplicateStudent->id,
                'public_id' => $this->duplicateStudent->public_id,
                'full_name' => $this->duplicateStudent->full_name,
            ]),
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'public_id' => $this->student->public_id,
                'full_name' => $this->student->full_name,
            ]),
            'error' => $this->error,
        ];
    }
}
