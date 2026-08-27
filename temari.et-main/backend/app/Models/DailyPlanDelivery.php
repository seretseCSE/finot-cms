<?php

namespace App\Models;

use App\Enums\LessonCoverage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One classroom sitting of a daily lesson plan (section × date × period).
 * Coverage is per sitting — the after-the-fact truth pacing sums over.
 */
#[Fillable([
    'daily_lesson_plan_id', 'section_id', 'teaches_on', 'period_number',
    'coverage', 'coverage_note',
])]
class DailyPlanDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'teaches_on' => 'date',
            'period_number' => 'integer',
            'coverage' => LessonCoverage::class,
        ];
    }

    /** @return BelongsTo<DailyLessonPlan, $this> */
    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyLessonPlan::class, 'daily_lesson_plan_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
