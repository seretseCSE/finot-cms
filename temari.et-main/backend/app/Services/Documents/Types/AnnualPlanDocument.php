<?php

namespace App\Services\Documents\Types;

use App\Models\AnnualLessonPlan;
use App\Models\GeneratedDocument;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\LessonPlans\LessonPlanAccess;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The official ANNUAL lesson plan sheet — the MoE semester grid (semester ×
 * month/week × unit with objectives, rationale, prerequisite knowledge,
 * aids, methodology, assessment, pages) with the header block and signature
 * lines supervisors expect on paper. Ethiopian months are derived from each
 * unit's date window, never typed.
 */
class AnnualPlanDocument extends DocumentType
{
    public function view(): string
    {
        return 'annual-plan';
    }

    public function landscape(): bool
    {
        return true;
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return AnnualLessonPlan::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        if (! $subject instanceof AnnualLessonPlan) {
            return false;
        }

        return LessonPlanAccess::isReviewer($user, $subject)
            || $user->hasPermissionForScope('lesson_plans.view', $subject->school_id, $subject->branch_id)
            || LessonPlanAccess::isOwner($user, $subject);
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var AnnualLessonPlan $subject */
        return ['school_id' => $subject->school_id, 'branch_id' => $subject->branch_id];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var AnnualLessonPlan $subject */
        $subject->load([
            'school:id,name,logo_path', 'branch:id,name',
            'academicYear:id,name', 'subject:id,code,name', 'gradeLevel:id,name',
            'employee:id,first_name,father_name,grandfather_name',
            'units.term:id,name,semester',
            'decider:id,name',
        ]);

        $units = $subject->units->map(function ($unit): array {
            $months = null;
            $weeks = null;

            if ($unit->starts_on !== null && $unit->ends_on !== null) {
                $start = CarbonImmutable::parse($unit->starts_on->toDateString());
                $end = CarbonImmutable::parse($unit->ends_on->toDateString());

                $from = EthiopianDate::fromGregorian($start);
                $to = EthiopianDate::fromGregorian($end);
                $months = $from['month'] === $to['month'] && $from['year'] === $to['year']
                    ? EthiopianDate::monthLabel($from['year'], $from['month'])
                    : EthiopianDate::monthLabel($from['year'], $from['month']).' – '.EthiopianDate::monthLabel($to['year'], $to['month']);
                $weeks = max(1, (int) ceil(($start->diffInDays($end) + 1) / 7));
            }

            return [
                'sequence' => $unit->sequence,
                'title' => $unit->title,
                'semester' => $unit->term?->semester,
                'term_name' => $unit->term?->name,
                'months' => $months,
                'weeks' => $weeks,
                'starts_on' => $unit->starts_on?->toDateString(),
                'ends_on' => $unit->ends_on?->toDateString(),
                'planned_periods' => $unit->planned_periods,
                'page_from' => $unit->page_from,
                'page_to' => $unit->page_to,
                'objectives' => $unit->objectives,
                'rationale' => $unit->rationale,
                'prerequisite_knowledge' => $unit->prerequisite_knowledge,
                'methods' => $unit->methods,
                'teaching_aids' => $unit->teaching_aids,
                'assessment_techniques' => $unit->assessment_techniques,
            ];
        })->values()->all();

        return [
            'school_name' => $subject->school?->name,
            'branch_name' => $subject->branch?->name,
            'year_name' => $subject->academicYear?->name,
            'subject_name' => $subject->subject?->name,
            'subject_code' => $subject->subject?->code,
            'grade_name' => $subject->gradeLevel?->name,
            'teacher_name' => $subject->employee?->full_name,
            'periods_per_week' => $subject->periods_per_week,
            'total_periods' => $subject->total_periods,
            'goals' => $subject->goals,
            'methods' => $subject->methods,
            'status' => $subject->status->value,
            'approved_by' => $subject->status->value === 'approved' ? $subject->decider?->name : null,
            'approved_at' => $subject->status->value === 'approved' ? $subject->decided_at?->toDateString() : null,
            'units' => $units,
        ];
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $plan = $document->subject;

        if (! $plan instanceof AnnualLessonPlan) {
            return [];
        }

        $plan->load(['subject:id,name', 'gradeLevel:id,name', 'academicYear:id,name']);

        return array_filter([
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'subject' => $plan->subject?->name,
            'grade' => $plan->gradeLevel?->name,
            'academic_year' => $plan->academicYear?->name,
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null);
    }
}
