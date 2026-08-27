<?php

namespace App\Models;

use App\Enums\LessonCoverage;
use App\Enums\LessonPlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One Monday-anchored week of an annual lesson plan, carrying the
 * submit → approve/decline workflow and the pacing-gate justification.
 */
#[Fillable([
    'annual_lesson_plan_id', 'school_id', 'branch_id', 'term_id', 'week_starts_on',
    'status', 'lag_justification', 'notes', 'submitted_at', 'submitted_by',
    'decided_at', 'decided_by', 'decline_reason',
])]
class WeeklyLessonPlan extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => LessonPlanStatus::class,
            'week_starts_on' => 'date',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AnnualLessonPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AnnualLessonPlan::class, 'annual_lesson_plan_id');
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return HasMany<DailyLessonPlan, $this> */
    public function dailyPlans(): HasMany
    {
        return $this->hasMany(DailyLessonPlan::class)->orderBy('teaches_on')->orderBy('sequence');
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

    /** Whether any sitting in this (approved) week is still uncovered. */
    public function hasUncoveredLessons(): bool
    {
        return DailyPlanDelivery::query()
            ->whereIn('daily_lesson_plan_id', $this->dailyPlans()->select('id'))
            ->whereIn('coverage', [LessonCoverage::Pending->value, LessonCoverage::Partial->value, LessonCoverage::Missed->value])
            ->exists();
    }
}
