<?php

namespace App\Models;

use App\Enums\StudentImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_import_id', 'row_number', 'data', 'status', 'issues',
    'duplicate_student_id', 'resolution', 'student_id', 'error',
])]
class StudentImportRow extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudentImportRowStatus::class,
            'data' => 'array',
            'issues' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StudentImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(StudentImport::class, 'student_import_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function duplicateStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'duplicate_student_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
