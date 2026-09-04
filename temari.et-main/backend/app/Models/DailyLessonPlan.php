<?php

namespace App\Models;

use App\Enums\LessonCoverage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One daily lesson plan (the MoE daily format) inside a weekly container.
 * Content lives here + in stages; the classroom sittings and their coverage
 * live in deliveries — one plan serves every section the teacher takes
 * through the same lesson.
 */
#[Fillable([
    'weekly_lesson_plan_id', 'annual_plan_unit_id', 'teaches_on', 'topic',
    'subtopic', 'rationale', 'prerequisite_knowledge', 'objectives',
    'support_slow', 'support_medium', 'support_fast', 'homework', 'sequence',
])]
class DailyLessonPlan extends Model
{
    protected function casts(): array
    {
        return [
            'teaches_on' => 'date',
            'sequence' => 'integer',
        ];
    }

    /** @return BelongsTo<WeeklyLessonPlan, $this> */
    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyLessonPlan::class, 'weekly_lesson_plan_id');
    }

    /** @return BelongsTo<AnnualPlanUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(AnnualPlanUnit::class, 'annual_plan_unit_id');
    }

    /** @return HasMany<DailyPlanStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(DailyPlanStage::class);
    }

    /** @return HasMany<DailyPlanDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(DailyPlanDelivery::class)->orderBy('teaches_on')->orderBy('period_number');
    }

    /** Any sitting still short of fully covered — what the pacing gate sees. */
    public function isUncovered(): bool
    {
        return $this->deliveries
            ->contains(fn (DailyPlanDelivery $d): bool => $d->coverage !== LessonCoverage::Covered);
    }
}
