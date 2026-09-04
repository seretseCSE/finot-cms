<?php

namespace App\Services\LessonPlans;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonPlanStatus;
use App\Models\AnnualLessonPlan;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectAssignment;
use App\Models\WeeklyLessonPlan;
use Carbon\CarbonImmutable;

/**
 * The family-facing lesson-plan view (ADR-012): one card per subject the
 * student sits in — teacher, syllabus progress, chapter timeline, and this
 * week's APPROVED lessons. Shared by MeLessonPlanController and the AI
 * family tools; only approved plans ever leave this method.
 */
class FamilyLessonPlanPayload
{
    public function __construct(private readonly LessonPlanPacing $pacing)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forStudent(Student $student): array
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('section_id')
            ->with('section:id,branch_id,grade_level_id')
            ->latest('id')
            ->first();

        if ($enrollment === null || $enrollment->section === null) {
            return ['subjects' => []];
        }

        $assignments = SubjectAssignment::query()
            ->where('section_id', $enrollment->section_id)
            ->where('is_active', true)
            ->whereNotNull('employee_id')
            ->with(['subject:id,code,name', 'employee:id,first_name,father_name,grandfather_name'])
            ->get()
            ->unique(fn (SubjectAssignment $a) => $a->subject_id.':'.$a->employee_id)
            ->values();

        if ($assignments->isEmpty()) {
            return ['subjects' => []];
        }

        $plans = AnnualLessonPlan::query()
            ->where('branch_id', $enrollment->section->branch_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('grade_level_id', $enrollment->section->grade_level_id)
            ->where('status', LessonPlanStatus::Approved->value)
            ->whereIn('subject_id', $assignments->pluck('subject_id'))
            ->whereIn('employee_id', $assignments->pluck('employee_id'))
            ->with('units:id,annual_lesson_plan_id,sequence,title,starts_on,ends_on,planned_periods')
            ->get()
            ->keyBy(fn (AnnualLessonPlan $plan) => $plan->subject_id.':'.$plan->employee_id);

        $summaries = $this->pacing->bulkSummaries($plans->pluck('id')->all());

        $weekStart = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);
        $sectionId = (int) $enrollment->section_id;
        $weeks = $plans->isEmpty() ? collect() : WeeklyLessonPlan::query()
            ->whereIn('annual_lesson_plan_id', $plans->pluck('id'))
            ->where('week_starts_on', $weekStart->toDateString())
            ->where('status', LessonPlanStatus::Approved->value)
            ->with(['dailyPlans.deliveries' => fn ($q) => $q->where('section_id', $sectionId)])
            ->get()
            ->keyBy('annual_lesson_plan_id');

        $today = CarbonImmutable::today();

        $subjects = $assignments->map(function (SubjectAssignment $a) use ($plans, $summaries, $weeks, $today): array {
            $plan = $plans->get($a->subject_id.':'.$a->employee_id);
            $summary = $plan !== null ? ($summaries[$plan->id] ?? null) : null;
            $week = $plan !== null ? $weeks->get($plan->id) : null;

            return [
                'subject' => ['id' => $a->subject?->id, 'code' => $a->subject?->code, 'name' => $a->subject?->name],
                'teacher_name' => $a->employee?->full_name,
                'has_plan' => $plan !== null,
                'progress_percent' => $summary['progress_percent'] ?? null,
                'units_total' => $summary['units_total'] ?? null,
                'units_done' => $summary['units_done'] ?? null,
                'units' => $plan?->units->map(fn ($unit): array => [
                    'sequence' => $unit->sequence,
                    'title' => $unit->title,
                    'starts_on' => $unit->starts_on?->toDateString(),
                    'ends_on' => $unit->ends_on?->toDateString(),
                    'is_current' => $unit->starts_on !== null && $unit->ends_on !== null
                        && $today->between($unit->starts_on, $unit->ends_on),
                    'is_past' => $unit->ends_on !== null && $today->gt($unit->ends_on),
                ])->values(),
                'current_week' => $week === null ? null : [
                    'week_starts_on' => $week->week_starts_on->toDateString(),
                    // Only lessons that touch THIS student's section, with the
                    // section's own sitting date and coverage.
                    'lessons' => $week->dailyPlans
                        ->filter(fn ($day) => $day->deliveries->isNotEmpty())
                        ->map(function ($day): array {
                            $sitting = $day->deliveries->first();

                            return [
                                'teaches_on' => $sitting->teaches_on->toDateString(),
                                'day_of_week' => $sitting->teaches_on->dayOfWeekIso,
                                'topic' => $day->topic,
                                'subtopic' => $day->subtopic,
                                'objectives' => $day->objectives,
                                'homework' => $day->homework,
                                'coverage' => $sitting->coverage->value,
                            ];
                        })->values(),
                ],
            ];
        })->sortBy(fn (array $row) => $row['subject']['name'])->values();

        return [
            'week_starts_on' => $weekStart->toDateString(),
            'subjects' => $subjects,
        ];
    }
}
