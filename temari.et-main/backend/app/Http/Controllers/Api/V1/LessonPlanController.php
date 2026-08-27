<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LessonPlanStatus;
use App\Http\Controllers\Controller;
use App\Models\AnnualLessonPlan;
use App\Models\AnnualPlanUnit;
use App\Models\Employee;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Models\WeeklyLessonPlan;
use App\Services\LessonPlans\LessonPlanAccess;
use App\Services\LessonPlans\LessonPlanPacing;
use App\Services\Notify\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The ANNUAL lesson plan lane (the general plan): a teacher's yearly roadmap
 * for one subject × grade, chaptered into units with timelines. Finalized
 * before the year starts through the same teacher-signs / leadership-
 * countersigns ritual as marklists — the director OR the principal decides,
 * each independently. Once approved it becomes the pacing baseline the weekly
 * lane (WeeklyLessonPlanController) is measured against.
 */
class LessonPlanController extends Controller
{
    /** The register: a teacher's own plans, or every plan in scope for supervisors. */
    public function index(Request $request, LessonPlanPacing $pacing): JsonResponse
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        $supervisor = $user->hasContextPermission('lesson_plans.view')
            || $user->hasContextPermission('lesson_plans.review')
            || LessonPlanAccess::isContextReviewer($user, $branch);

        abort_unless($supervisor || $user->hasContextPermission('lesson_plans.manage_own'), 403);

        $plans = AnnualLessonPlan::query()
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($branch === null, fn ($q) => $q->where('school_id', $this->activeSchoolScopeId($request) ?? 0))
            ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id))
            ->when(! $supervisor, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('user_id', $user->id)))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('grade_level_id'), fn ($q) => $q->where('grade_level_id', $request->integer('grade_level_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->with([
                'subject:id,code,name',
                'gradeLevel:id,name,sort_order',
                'academicYear:id,name,status',
                'employee:id,first_name,father_name,grandfather_name,user_id',
            ])
            ->withCount(['units', 'weeklyPlans'])
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 50), 100));

        $summaries = $pacing->bulkSummaries(collect($plans->items())->pluck('id')->all());

        return response()->json([
            'data' => collect($plans->items())->map(fn (AnnualLessonPlan $plan): array => [
                ...$this->planRow($plan, $user),
                'units_count' => (int) $plan->units_count,
                'weekly_plans_count' => (int) $plan->weekly_plans_count,
                'pacing' => $summaries[$plan->id] ?? null,
            ]),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    /**
     * The create-sheet's option list: the requesting teacher's own teaching
     * load as (year × subject × grade) combos, flagged where a plan already
     * exists — so the sheet only ever offers real classes.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->targetBranch($request);
        abort_unless($user->hasPermissionForScope('lesson_plans.manage_own', $branch->school_id, $branch->id), 403);

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branch->id)
            ->first();

        if ($employee === null) {
            return response()->json(['data' => []]);
        }

        $assignments = SubjectAssignment::query()
            ->where('subject_assignments.employee_id', $employee->id)
            ->where('subject_assignments.is_active', true)
            ->with([
                'subject:id,code,name',
                'section:id,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
            ])
            ->join('academic_years', 'academic_years.id', '=', 'subject_assignments.academic_year_id')
            ->whereIn('academic_years.status', ['planned', 'active'])
            ->select('subject_assignments.*', 'academic_years.name as year_name', 'academic_years.status as year_status')
            ->get();

        $plans = AnnualLessonPlan::query()
            ->where('employee_id', $employee->id)
            ->whereIn('academic_year_id', $assignments->pluck('academic_year_id')->unique())
            ->get(['id', 'academic_year_id', 'subject_id', 'grade_level_id'])
            ->keyBy(fn ($p) => $p->academic_year_id.':'.$p->subject_id.':'.$p->grade_level_id);

        $options = $assignments
            ->unique(fn (SubjectAssignment $a) => $a->academic_year_id.':'.$a->subject_id.':'.$a->section?->grade_level_id)
            ->map(function (SubjectAssignment $a) use ($plans): array {
                $key = $a->academic_year_id.':'.$a->subject_id.':'.$a->section?->grade_level_id;

                return [
                    'academic_year_id' => $a->academic_year_id,
                    'academic_year_name' => $a->year_name,
                    'academic_year_status' => $a->year_status,
                    'subject' => ['id' => $a->subject?->id, 'code' => $a->subject?->code, 'name' => $a->subject?->name],
                    'grade_level' => ['id' => $a->section?->grade_level_id, 'name' => $a->section?->gradeLevel?->name],
                    'grade_sort' => $a->section?->gradeLevel?->sort_order ?? 0,
                    'plan_id' => $plans->get($key)?->id,
                ];
            })
            ->sortBy([['grade_sort', 'asc']])
            ->values();

        return response()->json(['data' => $options]);
    }

    /**
     * A teacher opens an annual plan for a class they actually teach — the
     * plan must correspond to a real teaching load in that year.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['sometimes', 'integer'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'goals' => ['nullable', 'string', 'max:20000'],
            'methods' => ['nullable', 'string', 'max:20000'],
            'periods_per_week' => ['nullable', 'integer', 'min:1', 'max:60'],
            'total_periods' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $user = $request->user();
        $branch = $this->targetBranch($request);

        abort_unless($user->hasPermissionForScope('lesson_plans.manage_own', $branch->school_id, $branch->id), 403);

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branch->id)
            ->first();

        abort_if($employee === null, 422, 'No employee record is linked to your account at this branch.');

        $teaches = SubjectAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('is_active', true)
            ->whereHas('section', fn ($q) => $q->where('grade_level_id', $data['grade_level_id']))
            ->exists();

        abort_unless($teaches, 422, 'You have no teaching assignment for this subject and grade in the selected year.');

        $existing = AnnualLessonPlan::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('branch_id', $branch->id)
            ->where('subject_id', $data['subject_id'])
            ->where('grade_level_id', $data['grade_level_id'])
            ->where('employee_id', $employee->id)
            ->exists();

        abort_if($existing, 422, 'An annual plan for this subject and grade already exists.');

        $plan = AnnualLessonPlan::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'academic_year_id' => $data['academic_year_id'],
            'subject_id' => $data['subject_id'],
            'grade_level_id' => $data['grade_level_id'],
            'employee_id' => $employee->id,
            'goals' => $this->cleanRichText($data['goals'] ?? null),
            'methods' => $this->cleanRichText($data['methods'] ?? null),
            'periods_per_week' => $data['periods_per_week'] ?? null,
            'total_periods' => $data['total_periods'] ?? null,
            'status' => LessonPlanStatus::Draft,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'data' => $this->planRow($plan->load(['subject:id,code,name', 'gradeLevel:id,name,sort_order', 'academicYear:id,name,status', 'employee:id,first_name,father_name,grandfather_name,user_id']), $user),
            'message' => 'Annual plan created.',
        ], 201);
    }

    /** The full plan: units, weekly-plan summaries, pacing, capability flags. */
    public function show(Request $request, AnnualLessonPlan $lessonPlan, LessonPlanPacing $pacing): JsonResponse
    {
        $user = $request->user();
        LessonPlanAccess::assertViewer($user, $lessonPlan);

        $lessonPlan->load([
            'subject:id,code,name',
            'gradeLevel:id,name,sort_order',
            'academicYear:id,name,status',
            'employee:id,first_name,father_name,grandfather_name,user_id',
            'units' => fn ($q) => $q->withCount('lessons'),
            'units.term:id,name,semester',
            'weeklyPlans.dailyPlans:id,weekly_lesson_plan_id,teaches_on',
            'weeklyPlans.dailyPlans.deliveries:id,daily_lesson_plan_id,coverage',
            'submitter:id,name',
            'decider:id,name',
        ]);

        // The classes this plan teaches — the section choices for new daily
        // plans (the teacher's live assignments of this subject × grade).
        $sections = SubjectAssignment::query()
            ->where('employee_id', $lessonPlan->employee_id)
            ->where('academic_year_id', $lessonPlan->academic_year_id)
            ->where('subject_id', $lessonPlan->subject_id)
            ->where('is_active', true)
            ->whereHas('section', fn ($q) => $q->where('grade_level_id', $lessonPlan->grade_level_id))
            ->with('section:id,name')
            ->get()
            ->map(fn ($a) => $a->section)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($s): array => ['id' => $s->id, 'name' => $s->name]);

        return response()->json([
            'data' => [
                ...$this->planRow($lessonPlan, $user),
                'sections' => $sections,
                'units' => $lessonPlan->units->map(fn (AnnualPlanUnit $unit): array => $this->unitRow($unit)),
                'weekly_plans' => $lessonPlan->weeklyPlans->map(fn ($week): array => [
                    'id' => $week->id,
                    'week_starts_on' => $week->week_starts_on->toDateString(),
                    'status' => $week->status->value,
                    'lag_justified' => $week->lag_justification !== null,
                    'decline_reason' => $week->decline_reason,
                    'lessons_count' => $week->dailyPlans->count(),
                    'covered_count' => $week->dailyPlans
                        ->filter(fn ($day) => $day->deliveries->isNotEmpty() && ! $day->isUncovered())
                        ->count(),
                ]),
                'pacing' => $pacing->summary($lessonPlan),
            ],
        ]);
    }

    /** Goals/methods edits — the owner, while the plan is a draft or declined. */
    public function update(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        $user = $request->user();
        LessonPlanAccess::assertOwner($user, $lessonPlan);
        $this->assertEditable($lessonPlan);

        $data = $request->validate([
            'goals' => ['nullable', 'string', 'max:20000'],
            'methods' => ['nullable', 'string', 'max:20000'],
            'periods_per_week' => ['nullable', 'integer', 'min:1', 'max:60'],
            'total_periods' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $lessonPlan->update([
            'goals' => $this->cleanRichText($data['goals'] ?? $lessonPlan->goals),
            'methods' => $this->cleanRichText($data['methods'] ?? $lessonPlan->methods),
            'periods_per_week' => array_key_exists('periods_per_week', $data) ? $data['periods_per_week'] : $lessonPlan->periods_per_week,
            'total_periods' => array_key_exists('total_periods', $data) ? $data['total_periods'] : $lessonPlan->total_periods,
        ]);

        return response()->json(['data' => $this->planRow($lessonPlan, $user), 'message' => 'Annual plan updated.']);
    }

    public function destroy(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        LessonPlanAccess::assertOwner($request->user(), $lessonPlan);
        abort_unless($lessonPlan->status === LessonPlanStatus::Draft, 422, 'Only a draft plan can be deleted.');

        $lessonPlan->delete();

        return response()->json(['message' => 'Annual plan deleted.']);
    }

    /** Teacher sign-off: draft/declined → submitted. Needs at least one unit. */
    public function submit(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        $user = $request->user();
        LessonPlanAccess::assertOwner($user, $lessonPlan);
        $this->assertEditable($lessonPlan);

        abort_if($lessonPlan->units()->count() === 0, 422, 'Add at least one unit before submitting.');

        $lessonPlan->update([
            'status' => LessonPlanStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $user->id,
            'decided_at' => null,
            'decided_by' => null,
            'decline_reason' => null,
        ]);

        app(Notifier::class)->toStaff(
            $lessonPlan->school_id,
            $lessonPlan->branch_id,
            'lesson_plans.review',
            'academics.annual_plan_submitted',
            ['teacher' => $user->name, ...$this->planVars($lessonPlan)],
            ['link' => '/lesson-plans?tab=review', 'exceptUserId' => $user->id],
        );

        return response()->json(['data' => $this->planRow($lessonPlan, $user), 'message' => 'Annual plan submitted for review.']);
    }

    /** Reviewer countersign: submitted → approved. */
    public function approve(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        return $this->decide($request, $lessonPlan, approve: true);
    }

    /** Reviewer decline: submitted → declined, reason required. */
    public function decline(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->decide($request, $lessonPlan, approve: false);
    }

    /**
     * Back to draft: the owner may withdraw while awaiting review; unlocking
     * an APPROVED plan takes the review authority (its weekly plans stay).
     */
    public function reopen(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        $user = $request->user();

        $isReviewer = LessonPlanAccess::isReviewer($user, $lessonPlan);
        $isWithdrawal = $lessonPlan->status === LessonPlanStatus::Submitted
            && (int) $lessonPlan->submitted_by === (int) $user->id;

        abort_unless($isReviewer || $isWithdrawal, 403);
        abort_if($lessonPlan->status === LessonPlanStatus::Draft, 422, 'The plan is already a draft.');

        $submitterId = $lessonPlan->submitted_by;

        $lessonPlan->update([
            'status' => LessonPlanStatus::Draft,
            'submitted_at' => null,
            'submitted_by' => null,
            'decided_at' => null,
            'decided_by' => null,
            'decline_reason' => null,
        ]);

        if ($isReviewer && $submitterId !== null && (int) $submitterId !== (int) $user->id) {
            app(Notifier::class)->toUser(User::find($submitterId), 'academics.annual_plan_decided', [
                ...$this->planVars($lessonPlan),
                'status' => 'reopened',
            ], [
                'link' => '/lesson-plans/'.$lessonPlan->id,
                'schoolId' => $lessonPlan->school_id,
                'branchId' => $lessonPlan->branch_id,
            ]);
        }

        return response()->json(['data' => $this->planRow($lessonPlan, $user), 'message' => 'Annual plan reopened for editing.']);
    }

    // ───────────────────────── units ─────────────────────────

    public function storeUnit(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        LessonPlanAccess::assertOwner($request->user(), $lessonPlan);
        $this->assertEditable($lessonPlan);

        $data = $this->validateUnit($request, $lessonPlan);

        $unit = $lessonPlan->units()->create([
            ...$data,
            'school_id' => $lessonPlan->school_id,
            'branch_id' => $lessonPlan->branch_id,
            'sequence' => $data['sequence'] ?? ((int) $lessonPlan->units()->max('sequence') + 1),
        ]);

        return response()->json(['data' => $this->unitRow($unit->load('term:id,name,semester')), 'message' => 'Unit added.'], 201);
    }

    public function updateUnit(Request $request, AnnualPlanUnit $unit): JsonResponse
    {
        $plan = $unit->plan;
        LessonPlanAccess::assertOwner($request->user(), $plan);
        $this->assertEditable($plan);

        $unit->update($this->validateUnit($request, $plan));

        return response()->json(['data' => $this->unitRow($unit->fresh()->load('term:id,name,semester')), 'message' => 'Unit updated.']);
    }

    public function destroyUnit(Request $request, AnnualPlanUnit $unit): JsonResponse
    {
        $plan = $unit->plan;
        LessonPlanAccess::assertOwner($request->user(), $plan);
        $this->assertEditable($plan);

        $unit->delete();

        return response()->json(['message' => 'Unit removed.']);
    }

    // ───────────────────────── oversight ─────────────────────────

    /** The reviewer's inbox: every submitted annual + weekly plan in scope. */
    public function review(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        abort_unless(
            $user->hasContextPermission('lesson_plans.review') || LessonPlanAccess::isContextReviewer($user, $branch),
            403,
        );

        $scope = fn ($q) => $q
            ->when($branch !== null, fn ($qq) => $qq->where('branch_id', $branch->id))
            ->when($branch === null, fn ($qq) => $qq->where('school_id', $this->activeSchoolScopeId($request) ?? 0))
            ->when($this->branchFilterId($request, $branch), fn ($qq, $id) => $qq->where('branch_id', $id));

        $annual = AnnualLessonPlan::query()
            ->where('status', LessonPlanStatus::Submitted->value)
            ->tap($scope)
            ->with([
                'subject:id,code,name', 'gradeLevel:id,name,sort_order',
                'academicYear:id,name,status',
                'employee:id,first_name,father_name,grandfather_name,user_id',
            ])
            ->withCount('units')
            ->orderBy('submitted_at')
            ->limit(100)
            ->get();

        $weekly = WeeklyLessonPlan::query()
            ->where('status', LessonPlanStatus::Submitted->value)
            ->tap($scope)
            ->with([
                'plan.subject:id,code,name', 'plan.gradeLevel:id,name,sort_order',
                'plan.employee:id,first_name,father_name,grandfather_name,user_id',
            ])
            ->withCount('dailyPlans')
            ->orderBy('submitted_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => [
                'annual' => $annual->map(fn (AnnualLessonPlan $plan): array => [
                    ...$this->planRow($plan, $user),
                    'units_count' => (int) $plan->units_count,
                ]),
                'weekly' => $weekly->map(fn ($week): array => [
                    'id' => $week->id,
                    'annual_lesson_plan_id' => $week->annual_lesson_plan_id,
                    'week_starts_on' => $week->week_starts_on->toDateString(),
                    'submitted_at' => $week->submitted_at?->toISOString(),
                    'lag_justified' => $week->lag_justification !== null,
                    'lag_justification' => $week->lag_justification,
                    'lessons_count' => (int) $week->daily_plans_count,
                    'subject' => ['id' => $week->plan?->subject?->id, 'code' => $week->plan?->subject?->code, 'name' => $week->plan?->subject?->name],
                    'grade_level' => ['id' => $week->plan?->gradeLevel?->id, 'name' => $week->plan?->gradeLevel?->name],
                    'teacher_name' => $week->plan?->employee?->full_name,
                ]),
            ],
        ]);
    }

    /**
     * The pacing dashboard: planned vs covered vs expected per plan, with the
     * week-level accountability trail (declines, justified lags).
     */
    public function pacing(Request $request, LessonPlanPacing $pacing): JsonResponse
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        abort_unless(
            $user->hasContextPermission('lesson_plans.view')
            || $user->hasContextPermission('lesson_plans.review')
            || LessonPlanAccess::isContextReviewer($user, $branch),
            403,
        );

        $plans = AnnualLessonPlan::query()
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($branch === null, fn ($q) => $q->where('school_id', $this->activeSchoolScopeId($request) ?? 0))
            ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->where('status', LessonPlanStatus::Approved->value)
            ->with([
                'subject:id,code,name',
                'gradeLevel:id,name,sort_order',
                'employee:id,first_name,father_name,grandfather_name,user_id',
                'weeklyPlans:id,annual_lesson_plan_id,week_starts_on,status,lag_justification',
            ])
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        $summaries = $pacing->bulkSummaries($plans->pluck('id')->all());

        return response()->json([
            'data' => $plans->map(function (AnnualLessonPlan $plan) use ($summaries): array {
                $weeks = $plan->weeklyPlans;
                $latest = $weeks->sortByDesc('week_starts_on')->first();

                return [
                    'id' => $plan->id,
                    'subject' => ['id' => $plan->subject?->id, 'code' => $plan->subject?->code, 'name' => $plan->subject?->name],
                    'grade_level' => ['id' => $plan->gradeLevel?->id, 'name' => $plan->gradeLevel?->name],
                    'teacher_name' => $plan->employee?->full_name,
                    'pacing' => $summaries[$plan->id] ?? null,
                    'weeks_total' => $weeks->count(),
                    'weeks_approved' => $weeks->where('status', LessonPlanStatus::Approved)->count(),
                    'weeks_declined' => $weeks->where('status', LessonPlanStatus::Declined)->count(),
                    'weeks_justified' => $weeks->filter(fn ($w) => $w->lag_justification !== null)->count(),
                    'last_week_starts_on' => $latest?->week_starts_on?->toDateString(),
                    'last_week_status' => $latest?->status?->value,
                ];
            }),
        ]);
    }

    // ───────────────────────── internals ─────────────────────────

    private function decide(Request $request, AnnualLessonPlan $lessonPlan, bool $approve): JsonResponse
    {
        $user = $request->user();
        LessonPlanAccess::assertReviewer($user, $lessonPlan);
        abort_unless($lessonPlan->status === LessonPlanStatus::Submitted, 422, 'Only a submitted plan can be decided.');

        $submitterId = $lessonPlan->submitted_by;

        $lessonPlan->update([
            'status' => $approve ? LessonPlanStatus::Approved : LessonPlanStatus::Declined,
            'decided_at' => now(),
            'decided_by' => $user->id,
            'decline_reason' => $approve ? null : $request->string('reason')->toString(),
        ]);

        if ($submitterId !== null && (int) $submitterId !== (int) $user->id) {
            app(Notifier::class)->toUser(User::find($submitterId), 'academics.annual_plan_decided', [
                ...$this->planVars($lessonPlan),
                'status' => $approve ? 'approved' : 'declined',
            ], [
                'link' => '/lesson-plans/'.$lessonPlan->id,
                'schoolId' => $lessonPlan->school_id,
                'branchId' => $lessonPlan->branch_id,
            ]);
        }

        return response()->json([
            'data' => $this->planRow($lessonPlan, $user),
            'message' => $approve ? 'Annual plan approved.' : 'Annual plan declined.',
        ]);
    }

    private function assertEditable(AnnualLessonPlan $plan): void
    {
        abort_unless($plan->status->isEditable(), 422, 'This plan is awaiting review or already approved — reopen it first.');
    }

    /** @return array<string, mixed> */
    private function validateUnit(Request $request, AnnualLessonPlan $plan): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'methods' => ['nullable', 'string', 'max:10000'],
            'rationale' => ['nullable', 'string', 'max:10000'],
            'prerequisite_knowledge' => ['nullable', 'string', 'max:10000'],
            'teaching_aids' => ['nullable', 'string', 'max:10000'],
            'assessment_techniques' => ['nullable', 'string', 'max:10000'],
            'page_from' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'page_to' => ['nullable', 'integer', 'min:1', 'max:5000', 'gte:page_from'],
            'term_id' => [
                'nullable', 'integer',
                Rule::exists('terms', 'id')->where('academic_year_id', $plan->academic_year_id),
            ],
            'starts_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'planned_periods' => ['nullable', 'integer', 'min:0', 'max:500'],
            'sequence' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);
    }

    /** @return array{subject: string, grade: string} */
    private function planVars(AnnualLessonPlan $plan): array
    {
        $plan->loadMissing(['subject:id,name', 'gradeLevel:id,name']);

        return [
            'subject' => $plan->subject?->name ?? '',
            'grade' => $plan->gradeLevel?->name ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function planRow(AnnualLessonPlan $plan, User $user): array
    {
        return [
            'id' => $plan->id,
            'school_id' => $plan->school_id,
            'branch_id' => $plan->branch_id,
            'academic_year' => $plan->relationLoaded('academicYear') && $plan->academicYear !== null
                ? ['id' => $plan->academicYear->id, 'name' => $plan->academicYear->name, 'status' => $plan->academicYear->status]
                : ['id' => $plan->academic_year_id],
            'subject' => ['id' => $plan->subject?->id, 'code' => $plan->subject?->code, 'name' => $plan->subject?->name],
            'grade_level' => ['id' => $plan->gradeLevel?->id, 'name' => $plan->gradeLevel?->name],
            'teacher_name' => $plan->employee?->full_name,
            'goals' => $plan->goals,
            'methods' => $plan->methods,
            'periods_per_week' => $plan->periods_per_week,
            'total_periods' => $plan->total_periods,
            'status' => $plan->status->value,
            'submitted_at' => $plan->submitted_at?->toISOString(),
            'submitted_by_name' => $plan->relationLoaded('submitter') ? $plan->submitter?->name : null,
            'decided_at' => $plan->decided_at?->toISOString(),
            'decided_by_name' => $plan->relationLoaded('decider') ? $plan->decider?->name : null,
            'decline_reason' => $plan->decline_reason,
            'is_own' => $plan->isOwnedBy($user),
            'can_review' => LessonPlanAccess::isReviewer($user, $plan),
        ];
    }

    /** @return array<string, mixed> */
    private function unitRow(AnnualPlanUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'sequence' => $unit->sequence,
            'title' => $unit->title,
            'objectives' => $unit->objectives,
            'methods' => $unit->methods,
            'rationale' => $unit->rationale,
            'prerequisite_knowledge' => $unit->prerequisite_knowledge,
            'teaching_aids' => $unit->teaching_aids,
            'assessment_techniques' => $unit->assessment_techniques,
            'page_from' => $unit->page_from,
            'page_to' => $unit->page_to,
            'term' => $unit->relationLoaded('term') && $unit->term !== null
                ? ['id' => $unit->term->id, 'name' => $unit->term->name, 'semester' => $unit->term->semester]
                : ($unit->term_id !== null ? ['id' => $unit->term_id] : null),
            'starts_on' => $unit->starts_on?->toDateString(),
            'ends_on' => $unit->ends_on?->toDateString(),
            'planned_periods' => $unit->planned_periods,
            'lessons_count' => $unit->lessons_count !== null ? (int) $unit->lessons_count : null,
        ];
    }
}
