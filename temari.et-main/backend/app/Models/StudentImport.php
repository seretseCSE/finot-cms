<?php

namespace App\Models;

use App\Enums\StudentImportRowStatus;
use App\Enums\StudentImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'academic_year_id', 'grade_level_id', 'section_id',
    'school_program_id', 'file_name', 'status', 'column_map', 'options',
    'total_rows', 'imported_count', 'skipped_count', 'failed_count',
    'created_by', 'committed_at', 'finished_at',
])]
class StudentImport extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudentImportStatus::class,
            'column_map' => 'array',
            'options' => 'array',
            'committed_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function sendSms(): bool
    {
        return (bool) ($this->options['send_sms'] ?? false);
    }

    public function createStudentAccounts(): bool
    {
        return (bool) ($this->options['create_student_accounts'] ?? false);
    }

    /**
     * Rows the commit will actually write: clean rows plus duplicates the
     * registrar explicitly resolved as "create anyway" or "enroll existing".
     *
     * @return HasMany<StudentImportRow, $this>
     */
    public function importableRows(): HasMany
    {
        return $this->rows()
            ->where(function ($q): void {
                $q->where('status', StudentImportRowStatus::Ready->value)
                    ->orWhere(function ($dup): void {
                        $dup->where('status', StudentImportRowStatus::Duplicate->value)
                            ->whereIn('resolution', ['create', 'enroll_existing']);
                    });
            });
    }

    /**
     * @return HasMany<StudentImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(StudentImportRow::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
