<?php

namespace App\Models;

use App\Enums\LessonStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One teaching stage of a daily lesson plan (intro / main / conclusion) —
 * the row of the MoE stage table: contents, textbook page, teacher vs
 * student activity, assessment techniques, teaching aids, remark.
 */
#[Fillable([
    'daily_lesson_plan_id', 'stage', 'learning_contents', 'page',
    'teacher_activity', 'student_activity', 'assessment_techniques',
    'teaching_aids', 'remark',
])]
class DailyPlanStage extends Model
{
    protected function casts(): array
    {
        return [
            'stage' => LessonStage::class,
        ];
    }

    /** @return BelongsTo<DailyLessonPlan, $this> */
    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyLessonPlan::class, 'daily_lesson_plan_id');
    }
}
