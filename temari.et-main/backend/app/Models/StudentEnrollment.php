<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'student_id', 'school_id', 'branch_id', 'academic_year_id', 'school_program_id',
    'section_id', 'grade_level_id', 'previous_school_id', 'status', 'enrolled_on', 'exited_on',
])]
class StudentEnrollment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_on' => 'date',
            'exited_on' => 'date',
        ];
    }

    /**
     * Live = holds (pending) or occupies (active) a seat this year. Pending
     * rows appear on no roster but block duplicate registration.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value]);
    }

    /**
     * @return HasMany<StudentTermResult, $this>
     */
    public function termResults(): HasMany
    {
        return $this->hasMany(StudentTermResult::class);
    }

    /**
     * @return HasMany<StudentPromotion, $this>
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'from_enrollment_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolProgram, $this>
     */
    public function schoolProgram(): BelongsTo
    {
        return $this->belongsTo(SchoolProgram::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<SchoolDirectoryEntry, $this>
     */
    public function previousSchool(): BelongsTo
    {
        return $this->belongsTo(SchoolDirectoryEntry::class, 'previous_school_id');
    }
}
