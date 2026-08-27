<?php

namespace App\Services\Documents\Types;

use App\Models\DailyLessonPlan;
use App\Models\GeneratedDocument;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\LessonPlans\LessonPlanAccess;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The official DAILY lesson plan sheet — the MoE daily format: the header
 * block (teacher, subject, grade, period, E.C. date, unit & topic,
 * rationale, prerequisites, objective), the three-stage table (teacher vs
 * student activity, assessment, aids), the special-need learner supports,
 * and the teacher / department head / curriculum v. principal signature
 * lines.
 */
class DailyLessonPlanDocument extends DocumentType
{
    public function view(): string
    {
        return 'daily-lesson-plan';
    }

    public function landscape(): bool
    {
        return true;
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return DailyLessonPlan::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        if (! $subject instanceof DailyLessonPlan) {
            return false;
        }

        $plan = $subject->weeklyPlan?->plan;

        if ($plan === null) {
            return false;
        }

        return LessonPlanAccess::isReviewer($user, $plan)
            || $user->hasPermissionForScope('lesson_plans.view', $plan->school_id, $plan->branch_id)
            || LessonPlanAccess::isOwner($user, $plan);
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var DailyLessonPlan $subject */
        $plan = $subject->weeklyPlan?->plan;

        return ['school_id' => $plan?->school_id, 'branch_id' => $plan?->branch_id];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var DailyLessonPlan $subject */
        $subject->load([
            'stages',
            'deliveries.section:id,name',
            'unit:id,title,sequence,page_from,page_to',
            'weeklyPlan:id,annual_lesson_plan_id,week_starts_on,status,decided_by',
            'weeklyPlan.decider:id,name',
            'weeklyPlan.plan.school:id,name,logo_path',
            'weeklyPlan.plan.branch:id,name',
            'weeklyPlan.plan.academicYear:id,name',
            'weeklyPlan.plan.subject:id,code,name',
            'weeklyPlan.plan.gradeLevel:id,name',
            'weeklyPlan.plan.employee:id,first_name,father_name,grandfather_name',
        ]);

        $plan = $subject->weeklyPlan->plan;
        $ec = EthiopianDate::fromGregorian(CarbonImmutable::parse($subject->teaches_on->toDateString()));

        $stages = $subject->stages
            ->sortBy(fn ($s) => $s->stage->sortOrder())
            ->map(fn ($s): array => [
                'stage' => $s->stage->value,
                'label' => $s->stage->label(),
                'learning_contents' => $s->learning_contents,
                'page' => $s->page,
                'teacher_activity' => $s->teacher_activity,
                'student_activity' => $s->student_activity,
                'assessment_techniques' => $s->assessment_techniques,
                'teaching_aids' => $s->teaching_aids,
                'remark' => $s->remark,
            ])->values()->all();

        return [
            'school_name' => $plan->school?->name,
            'branch_name' => $plan->branch?->name,
            'year_name' => $plan->academicYear?->name,
            'subject_name' => $plan->subject?->name,
            'grade_name' => $plan->gradeLevel?->name,
            'teacher_name' => $plan->employee?->full_name,
            'teaches_on' => $subject->teaches_on->toDateString(),
            'ec_date' => $ec['day'].'/'.$ec['month'].'/'.$ec['year'].' E.C',
            'ec_label' => EthiopianDate::monthLabel($ec['year'], $ec['month']).' '.$ec['day'],
            'unit_title' => $subject->unit?->title,
            'topic' => $subject->topic,
            'subtopic' => $subject->subtopic,
            'rationale' => $subject->rationale,
            'prerequisite_knowledge' => $subject->prerequisite_knowledge,
            'objectives' => $subject->objectives,
            'support_slow' => $subject->support_slow,
            'support_medium' => $subject->support_medium,
            'support_fast' => $subject->support_fast,
            'homework' => $subject->homework,
            'stages' => $stages,
            'sittings' => $subject->deliveries->map(fn ($d): array => [
                'section' => $d->section?->name,
                'teaches_on' => $d->teaches_on->toDateString(),
                'period_number' => $d->period_number,
            ])->values()->all(),
            'week_status' => $subject->weeklyPlan->status->value,
            'approved_by' => $subject->weeklyPlan->status->value === 'approved'
                ? $subject->weeklyPlan->decider?->name
                : null,
        ];
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $day = $document->subject;

        if (! $day instanceof DailyLessonPlan) {
            return [];
        }

        $plan = $day->weeklyPlan?->plan;
        $plan?->load(['subject:id,name', 'gradeLevel:id,name']);

        return array_filter([
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'subject' => $plan?->subject?->name,
            'grade' => $plan?->gradeLevel?->name,
            'date' => $day->teaches_on->toDateString(),
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null);
    }
}
