<?php

namespace App\Http\Resources;

use App\Models\StudentImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentImport
 */
class StudentImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id, 'name' => $this->branch->name,
            ]),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id, 'name' => $this->academicYear->name,
            ]),
            'grade_level_id' => $this->grade_level_id,
            'section_id' => $this->section_id,
            'school_program_id' => $this->school_program_id,
            'column_map' => $this->column_map,
            'options' => [
                'send_sms' => $this->sendSms(),
                'create_student_accounts' => $this->createStudentAccounts(),
            ],
            'total_rows' => $this->total_rows,
            'imported_count' => $this->imported_count,
            'skipped_count' => $this->skipped_count,
            'failed_count' => $this->failed_count,
            'row_stats' => $this->when(
                isset($this->resource->row_stats),
                fn () => $this->resource->row_stats,
            ),
            'importable_count' => $this->when(
                isset($this->resource->importable_count),
                fn () => $this->resource->importable_count,
            ),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id, 'name' => $this->creator->name,
            ]),
            'committed_at' => $this->committed_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
