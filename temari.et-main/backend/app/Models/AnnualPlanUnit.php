<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One chapter/unit of an annual lesson plan — its planned date window and
 * period count are what pacing is measured against.
 */
#[Fillable([
    'annual_lesson_plan_id', 'school_id', 'branch_id', 'term_id', 'sequence',
    'title', 'objectives', 'methods', 'rationale', 'prerequisite_knowledge',
    'teaching_aids', 'assessment_techniques', 'page_from', 'page_to',
    'starts_on', 'ends_on', 'planned_periods',
])]
class AnnualPlanUnit extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_periods' => 'integer',
            'page_from' => 'integer',
            'page_to' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
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
    public function lessons(): HasMany
    {
        return $this->hasMany(DailyLessonPlan::class, 'annual_plan_unit_id');
    }
}
