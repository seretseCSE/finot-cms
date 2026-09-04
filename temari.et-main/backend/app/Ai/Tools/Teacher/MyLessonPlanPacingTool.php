<?php

namespace App\Ai\Tools\Teacher;

use App\Models\AnnualLessonPlan;
use App\Services\LessonPlans\LessonPlanPacing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The teacher's own lesson-plan pacing: per annual plan — status, unit
 * timeline progress, and how far behind coverage is. Grounds "am I on
 * track?" and drafting the next weekly plan.
 */
class MyLessonPlanPacingTool extends TeacherScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get the teacher\'s own lesson plans and pacing: per subject/grade plan — approval status, units done vs total, progress percent, and current unit. Use for questions about syllabus coverage or planning the next week.';
    }

    public function handle(Request $request): Stringable|string
    {
        $plans = AnnualLessonPlan::query()
            ->whereIn('employee_id', $this->employeeIds())
            ->with(['subject:id,name', 'gradeLevel:id,name', 'units:id,annual_lesson_plan_id,sequence,title,starts_on,ends_on'])
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        if ($plans->isEmpty()) {
            return $this->deny('No lesson plans found for you in this context.');
        }

        $summaries = app(LessonPlanPacing::class)->bulkSummaries($plans->pluck('id')->all());
        $today = now()->toImmutable();

        return $this->ok($plans->map(function (AnnualLessonPlan $plan) use ($summaries, $today): array {
            $summary = $summaries[$plan->id] ?? null;
            $currentUnit = $plan->units->first(fn ($unit) => $unit->starts_on !== null && $unit->ends_on !== null
                && $today->between($unit->starts_on, $unit->ends_on));

            return [
                'plan_id' => $plan->id,
                'subject' => $plan->subject?->name,
                'grade' => $plan->gradeLevel?->name,
                'status' => $plan->status instanceof \BackedEnum ? $plan->status->value : $plan->status,
                'progress_percent' => $summary['progress_percent'] ?? null,
                'units_total' => $summary['units_total'] ?? null,
                'units_done' => $summary['units_done'] ?? null,
                'current_unit' => $currentUnit?->title,
                'units' => $plan->units->map(fn ($unit): array => [
                    'sequence' => $unit->sequence,
                    'title' => $unit->title,
                    'starts_on' => $unit->starts_on?->toDateString(),
                    'ends_on' => $unit->ends_on?->toDateString(),
                ])->values(),
            ];
        })->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
