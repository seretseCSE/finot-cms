<?php

namespace App\Models;

use App\Enums\LessonPlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The teacher's yearly roadmap for one subject × grade (one plan covers all
 * their sections of that class). Its units are the pacing baseline; weekly
 * plans hang off it and must tally with the unit timeline.
 */
#[Fillable([
    'school_id', 'branch_id', 'academic_year_id', 'subject_id', 'grade_level_id',
    'employee_id', 'goals', 'methods', 'periods_per_week', 'total_periods',
    'status', 'submitted_at', 'submitted_by',
    'decided_at', 'decided_by', 'decline_reason', 'created_by',
])]
class AnnualLessonPlan extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => LessonPlanStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<GradeLevel, $this> */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<AnnualPlanUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(AnnualPlanUnit::class)->orderBy('sequence');
    }

    /** @return HasMany<WeeklyLessonPlan, $this> */
    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(WeeklyLessonPlan::class)->orderBy('week_starts_on');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Ownership lane (`lesson_plans.manage_own`): the plan belongs to the
     * teacher whose employee file it is pinned to.
     */
    public function isOwnedBy(User $user): bool
    {
        $this->loadMissing('employee:id,user_id');

        return $this->employee !== null && (int) $this->employee->user_id === (int) $user->id;
    }
}
