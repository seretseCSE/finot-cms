<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'grade_level_id',
    'name', 'room_number', 'capacity', 'is_active',
])]
class Section extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * Year-scoped homeroom assignments (one row per academic year).
     *
     * @return HasMany<SectionHomeroom, $this>
     */
    public function homerooms(): HasMany
    {
        return $this->hasMany(SectionHomeroom::class);
    }

    /** Set/replace/clear the homeroom teacher for one academic year. */
    public function setHomeroom(int $academicYearId, ?int $employeeId): void
    {
        if ($employeeId === null) {
            $this->homerooms()->where('academic_year_id', $academicYearId)->delete();

            return;
        }

        $this->homerooms()->updateOrCreate(
            ['academic_year_id' => $academicYearId],
            ['employee_id' => $employeeId],
        );
    }

    /**
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * @return HasMany<SubjectAssignment, $this>
     */
    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(SubjectAssignment::class);
    }

    /**
     * Whether the user holds this section's homeroom in an active academic
     * year. Drives the attendance ownership lane — only the homeroom teacher
     * may open or mark the class register, never subject teachers.
     */
    public function isHomeroomedBy(User $user): bool
    {
        $employeeIds = Employee::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $this->branch_id)
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return false;
        }

        return $this->homerooms()
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->exists();
    }

    /**
     * Whether the user is this section's homeroom teacher or teaches a subject
     * in it — the broad ownership check behind `sections.view_own`.
     */
    public function isTaughtOrHomeroomedBy(User $user): bool
    {
        if ($this->isHomeroomedBy($user)) {
            return true;
        }

        $employeeIds = Employee::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $this->branch_id)
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return false;
        }

        return $this->subjectAssignments()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->exists();
    }
}
