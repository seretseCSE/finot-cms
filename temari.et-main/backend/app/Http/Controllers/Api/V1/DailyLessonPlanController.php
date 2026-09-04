<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LessonCoverage;
use App\Enums\LessonPlanStatus;
use App\Enums\LessonStage;
use App\Enums\TimetableVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AnnualLessonPlan;
use App\Models\DailyLessonPlan;
use App\Models\DailyPlanDelivery;
use App\Models\Employee;
use App\Models\Section;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Models\WeeklyLessonPlan;
use App\Services\LessonPlans\LessonPlanAccess;
use App\Support\TermGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The DAILY lesson plan lane — the MoE daily format, timetable-driven.
 *
 * `myDay` is the teacher's home surface: their real periods for a date
 * (from the published timetable), each showing its plan / plan-me state,
 * with one-tap coverage after class. A daily plan is CONTENT (topic,
 * stages, differentiation) written once; its deliveries pin it to the
 * actual sittings (section × date × period) where coverage is marked.
 * Creating a day auto-resolves the Monday-anchored weekly container —
 * teachers never manage containers by hand.
 */
class DailyLessonPlanController extends Controller
{
    /**
     * The teacher's day: periods from the published timetable × plan state.
     * Falls back to the plain class list when no timetable is published.
     */
    public function myDay(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        abort_if($branch === null, 422, 'Pick a branch workspace to see your teaching day.');
        abort_unless($user->hasPermissionForScope('lesson_plans.manage_own', $branch->school_id, $branch->id), 403);

        $request->validate(['date' => ['sometimes', 'date', 'after:2000-01-01', 'before:2100-01-01']]);
        $date = CarbonImmutable::parse($request->string('date', CarbonImmutable::today()->toDateString())->toString());

        $employee = Employee::query()->where('user_id', $user->id)->where('branch_id', $branch->id)->first();

        if ($employee === null) {
            return response()->json(['data' => ['date' => $date->toDateString(), 'items' => [], 'has_timetable' => false, 'periods' => []]]);
        }

        $term = Term::query()
            ->whereHas('academicYear', fn ($q) => $q->where('school_id', $branch->school_id))
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->orderBy('sequence')
            ->first();

        $version = $term === null ? null : TimetableVersion::query()
            ->where('branch_id', $branch->id)
            ->where('term_id', $term->id)
            ->where('status', TimetableVersionStatus::Published)
            ->latest('published_at')
            ->first();

        // The teacher's slots on this weekday, from the published grid.
        $slots = $version === null ? collect() : TimetableSlot::query()
            ->where('timetable_version_id', $version->id)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->whereHas('subjectAssignment', fn ($q) => $q->where('employee_id', $employee->id)->where('is_active', true))
            ->with(['subjectAssignment.subject:id,code,name', 'subjectAssignment.section:id,name,grade_level_id', 'subjectAssignment.section.gradeLevel:id,name,sort_order'])
            ->orderBy('period_number')
            ->get();

        // No published timetable → offer the class list so planning still works.
        $fallbackClasses = $slots->isNotEmpty() ? collect() : $employee->subjectAssignments()
            ->where('is_active', true)
            ->whereIn('academic_year_id', AcademicYear::query()->whereIn('status', ['planned', 'active'])->select('id'))
            ->with(['subject:id,code,name', 'section:id,name,grade_level_id', 'section.gradeLevel:id,name,sort_order'])
            ->get();

        $periods = $term === null ? collect() : TermPeriod::query()
            ->where('term_id', $term->id)
            ->where('type', 'class')
            ->orderBy('sequence')
            ->get(['period_number', 'starts_at', 'ends_at']);

        // Annual plans for every (subject × grade) the day touches.
        $combos = $slots->map(fn ($s) => [$s->subjectAssignment->subject_id, $s->subjectAssignment->section?->grade_level_id])
            ->merge($fallbackClasses->map(fn ($a) => [$a->subject_id, $a->section?->grade_level_id]))
            ->filter(fn ($c) => $c[1] !== null)
            ->unique(fn ($c) => $c[0].':'.$c[1]);

        $plans = $combos->isEmpty() ? collect() : AnnualLessonPlan::query()
            ->where('employee_id', $employee->id)
            ->where('branch_id', $branch->id)
            ->when($term !== null, fn ($q) => $q->where('academic_year_id', $term->academic_year_id))
            ->where(function ($q) use ($combos): void {
                foreach ($combos as $c) {
                    $q->orWhere(fn ($qq) => $qq->where('subject_id', $c[0])->where('grade_level_id', $c[1]));
                }
            })
            ->with('units:id,annual_lesson_plan_id,sequence,title,starts_on,ends_on,page_from,page_to')
            ->get()
            ->keyBy(fn ($p) => $p->subject_id.':'.$p->grade_level_id);

        $weekStart = $date->startOfWeek(CarbonImmutable::MONDAY);
        $weeks = $plans->isEmpty() ? collect() : WeeklyLessonPlan::query()
            ->whereIn('annual_lesson_plan_id', $plans->pluck('id'))
            ->where('week_starts_on', $weekStart->toDateString())
            ->get()
            ->keyBy('annual_lesson_plan_id');

        // Today's sittings across those plans, matched to slots below.
        $deliveries = $plans->isEmpty() ? collect() : DailyPlanDelivery::query()
            ->whereDate('teaches_on', $date->toDateString())
            ->whereHas('dailyPlan.weeklyPlan', fn ($q) => $q->whereIn('annual_lesson_plan_id', $plans->pluck('id')))
            ->with(['dailyPlan:id,weekly_lesson_plan_id,annual_plan_unit_id,topic,subtopic', 'dailyPlan.weeklyPlan:id,annual_lesson_plan_id,status'])
            ->get();

        $matched = [];
        $itemFor = function ($subject, $section, ?int $periodNumber, $periodRow) use ($plans, $weeks, $deliveries, $date, &$matched): array {
            $plan = $plans->get($subject?->id.':'.$section?->grade_level_id);
            $week = $plan !== null ? $weeks->get($plan->id) : null;

            $delivery = $deliveries->first(function (DailyPlanDelivery $d) use ($section, $periodNumber, $plan, &$matched): bool {
                if (in_array($d->id, $matched, true) || $plan === null) {
                    return false;
                }
                $planId = $d->dailyPlan?->weeklyPlan?->annual_lesson_plan_id;

                return (int) $d->section_id === (int) $section?->id
                    && (int) $planId === (int) $plan->id
                    && ($periodNumber === null || $d->period_number === null || (int) $d->period_number === $periodNumber);
            });

            if ($delivery !== null) {
                $matched[] = $delivery->id;
            }

            $suggestedUnit = $plan?->units
                ->first(fn ($u) => $u->starts_on !== null && $u->ends_on !== null && $date->between($u->starts_on, $u->ends_on));

            return [
                'period_number' => $periodNumber,
                'starts_at' => $periodRow?->starts_at,
                'ends_at' => $periodRow?->ends_at,
                'subject' => ['id' => $subject?->id, 'code' => $subject?->code, 'name' => $subject?->name],
                'section' => ['id' => $section?->id, 'name' => $section?->name],
                'grade_level' => ['id' => $section?->grade_level_id, 'name' => $section?->gradeLevel?->name],
                'plan' => $plan === null ? null : ['id' => $plan->id, 'status' => $plan->status->value],
                'week' => $week === null ? null : ['id' => $week->id, 'status' => $week->status->value],
                'daily' => $delivery === null ? null : [
                    'id' => $delivery->dailyPlan?->id,
                    'topic' => $delivery->dailyPlan?->topic,
                    'subtopic' => $delivery->dailyPlan?->subtopic,
                    'delivery_id' => $delivery->id,
                    'coverage' => $delivery->coverage->value,
                    'coverage_note' => $delivery->coverage_note,
                ],
                'suggested_unit' => $suggestedUnit === null ? null : [
                    'id' => $suggestedUnit->id,
                    'sequence' => $suggestedUnit->sequence,
                    'title' => $suggestedUnit->title,
                    'page_from' => $suggestedUnit->page_from,
                    'page_to' => $suggestedUnit->page_to,
                ],
            ];
        };

        $periodByNumber = $periods->keyBy('period_number');

        $items = $slots
            ->map(fn (TimetableSlot $slot): array => $itemFor(
                $slot->subjectAssignment?->subject,
                $slot->subjectAssignment?->section,
                (int) $slot->period_number,
                $periodByNumber->get($slot->period_number),
            ))
            ->concat($fallbackClasses->map(fn ($a): array => $itemFor($a->subject, $a->section, null, null)))
            ->values();

        // Sittings recorded today that no slot claimed (moved lessons etc.).
        $extras = $deliveries
            ->reject(fn (DailyPlanDelivery $d) => in_array($d->id, $matched, true))
            ->map(function (DailyPlanDelivery $d) use ($plans): array {
                $planId = $d->dailyPlan?->weeklyPlan?->annual_lesson_plan_id;
                $plan = $plans->first(fn ($p) => (int) $p->id === (int) $planId);

                return [
                    'period_number' => $d->period_number,
                    'starts_at' => null,
                    'ends_at' => null,
                    'subject' => ['id' => $plan?->subject_id, 'code' => null, 'name' => null],
                    'section' => ['id' => $d->section_id, 'name' => $d->section?->name],
                    'grade_level' => ['id' => $plan?->grade_level_id, 'name' => null],
                    'plan' => $plan === null ? null : ['id' => $plan->id, 'status' => $plan->status->value],
                    'week' => null,
                    'daily' => [
                        'id' => $d->dailyPlan?->id,
                        'topic' => $d->dailyPlan?->topic,
                        'subtopic' => $d->dailyPlan?->subtopic,
                        'delivery_id' => $d->id,
                        'coverage' => $d->coverage->value,
                        'coverage_note' => $d->coverage_note,
                    ],
                    'suggested_unit' => null,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'week_starts_on' => $weekStart->toDateString(),
                'has_timetable' => $slots->isNotEmpty(),
                'term' => $term === null ? null : ['id' => $term->id, 'name' => $term->name, 'status' => $term->status],
                'periods' => $periods->map(fn ($p): array => [
                    'period_number' => (int) $p->period_number,
                    'starts_at' => $p->starts_at,
                    'ends_at' => $p->ends_at,
                ])->values(),
                'items' => $items->concat($extras)->values(),
            ],
        ]);
    }

    /**
     * Create a daily plan under an annual plan. The Monday-anchored weekly
     * container is resolved (or drafted) automatically from the date.
     */
    public function store(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        $user = $request->user();
        LessonPlanAccess::assertOwner($user, $lessonPlan);

        $data = $this->validateDay($request, $lessonPlan);

        $teachesOn = CarbonImmutable::parse($data['teaches_on']);
        $weekStart = $teachesOn->startOfWeek(CarbonImmutable::MONDAY);

        $week = $lessonPlan->weeklyPlans()->where('week_starts_on', $weekStart->toDateString())->first();

        if ($week === null) {
            $term = WeeklyLessonPlanController::termFor($lessonPlan, $weekStart);
            TermGate::assertWritable($term);

            $week = $lessonPlan->weeklyPlans()->create([
                'school_id' => $lessonPlan->school_id,
                'branch_id' => $lessonPlan->branch_id,
                'term_id' => $term?->id,
                'week_starts_on' => $weekStart->toDateString(),
                'status' => LessonPlanStatus::Draft,
            ]);
        } else {
            abort_unless($week->status->isEditable(), 422, 'This week is awaiting review or approved — reopen it to add lessons.');
            TermGate::assertWritable($week->term);
        }

        $day = DB::transaction(function () use ($week, $data, $teachesOn) {
            $day = $week->dailyPlans()->create([
                ...$this->dayAttributes($data),
                'teaches_on' => $teachesOn->toDateString(),
                'sequence' => (int) $week->dailyPlans()->whereDate('teaches_on', $teachesOn->toDateString())->max('sequence') + 1,
            ]);

            $this->syncStages($day, $data['stages'] ?? []);
            $this->syncDeliveries($day, $data['deliveries'], $teachesOn);

            return $day;
        });

        return response()->json([
            'data' => $this->dayPayload($day->fresh()->load($this->dayRelations()), $user),
            'message' => 'Daily lesson plan created.',
        ], 201);
    }

    /** The full daily plan — studio + review reading. */
    public function show(Request $request, DailyLessonPlan $dailyPlan): JsonResponse
    {
        $plan = $dailyPlan->weeklyPlan->plan;
        LessonPlanAccess::assertViewer($request->user(), $plan);

        $dailyPlan->load($this->dayRelations());
        $plan->load(['subject:id,code,name', 'gradeLevel:id,name,sort_order', 'employee:id,first_name,father_name,grandfather_name,user_id']);

        // The teacher's live sections of this subject × grade — the sitting
        // choices in the studio.
        $sections = SubjectAssignment::query()
            ->where('employee_id', $plan->employee_id)
            ->where('academic_year_id', $plan->academic_year_id)
            ->where('subject_id', $plan->subject_id)
            ->where('is_active', true)
            ->whereHas('section', fn ($q) => $q->where('grade_level_id', $plan->grade_level_id))
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
                ...$this->dayPayload($dailyPlan, $request->user()),
                'plan' => [
                    'id' => $plan->id,
                    'status' => $plan->status->value,
                    'subject' => ['id' => $plan->subject?->id, 'code' => $plan->subject?->code, 'name' => $plan->subject?->name],
                    'grade_level' => ['id' => $plan->gradeLevel?->id, 'name' => $plan->gradeLevel?->name],
                    'teacher_name' => $plan->employee?->full_name,
                ],
                'sections' => $sections,
                'units' => $plan->units()->get(['id', 'sequence', 'title', 'page_from', 'page_to'])
                    ->map(fn ($u): array => ['id' => $u->id, 'sequence' => $u->sequence, 'title' => $u->title, 'page_from' => $u->page_from, 'page_to' => $u->page_to]),
            ],
        ]);
    }

    /** Autosave edits from the studio — fields, stages, deliveries. */
    public function update(Request $request, DailyLessonPlan $dailyPlan): JsonResponse
    {
        $user = $request->user();
        $week = $dailyPlan->weeklyPlan;
        $plan = $week->plan;
        LessonPlanAccess::assertOwner($user, $plan);
        abort_unless($week->status->isEditable(), 422, 'This week is awaiting review or approved — reopen it first.');
        TermGate::assertWritable($week->term);

        $data = $this->validateDay($request, $plan, updating: true);

        DB::transaction(function () use ($dailyPlan, $data, $week): void {
            $attributes = [];
            foreach ($this->dayAttributes($data, partial: true) as $key => $value) {
                $attributes[$key] = $value;
            }

            if (array_key_exists('teaches_on', $data)) {
                $teachesOn = CarbonImmutable::parse($data['teaches_on']);
                abort_unless(
                    $teachesOn->startOfWeek(CarbonImmutable::MONDAY)->toDateString() === $week->week_starts_on->toDateString(),
                    422,
                    'The lesson date must stay inside its week — use duplicate to plan another week.',
                );
                $attributes['teaches_on'] = $teachesOn->toDateString();
            }

            if ($attributes !== []) {
                $dailyPlan->update($attributes);
            }

            if (array_key_exists('stages', $data)) {
                $this->syncStages($dailyPlan, $data['stages'] ?? []);
            }

            if (array_key_exists('deliveries', $data)) {
                $this->syncDeliveries($dailyPlan, $data['deliveries'], CarbonImmutable::parse($dailyPlan->teaches_on));
            }
        });

        return response()->json([
            'data' => $this->dayPayload($dailyPlan->fresh()->load($this->dayRelations()), $user),
            'message' => 'Daily lesson plan updated.',
        ]);
    }

    public function destroy(Request $request, DailyLessonPlan $dailyPlan): JsonResponse
    {
        $week = $dailyPlan->weeklyPlan;
        LessonPlanAccess::assertOwner($request->user(), $week->plan);
        abort_unless($week->status->isEditable(), 422, 'This week is awaiting review or approved — reopen it first.');

        $dailyPlan->delete();

        return response()->json(['message' => 'Daily lesson plan deleted.']);
    }

    /**
     * After class: mark what happened, per sitting. Allowed once the week is
     * filed (submitted/approved) — reality is recorded regardless of how
     * fast the reviewer moved.
     */
    public function coverage(Request $request, DailyLessonPlan $dailyPlan): JsonResponse
    {
        $user = $request->user();
        $week = $dailyPlan->weeklyPlan;
        LessonPlanAccess::assertOwner($user, $week->plan);
        abort_if($week->status->isEditable(), 422, 'Submit the week before marking coverage.');
        TermGate::assertWritable($week->term);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_id' => ['required', 'integer'],
            'items.*.coverage' => ['required', Rule::enum(LessonCoverage::class)],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $deliveries = $dailyPlan->deliveries()->whereIn('id', collect($data['items'])->pluck('delivery_id'))->get()->keyBy('id');

        foreach ($data['items'] as $item) {
            $delivery = $deliveries->get((int) $item['delivery_id']);
            abort_if($delivery === null, 422, 'One of the sittings does not belong to this lesson.');

            $delivery->update([
                'coverage' => $item['coverage'],
                'coverage_note' => $this->trimmedOrNull($item['note'] ?? null),
            ]);
        }

        return response()->json([
            'data' => $this->dayPayload($dailyPlan->fresh()->load($this->dayRelations()), $user),
            'message' => 'Coverage recorded.',
        ]);
    }

    /**
     * Copy a lesson to another date (same or another week): the bump for an
     * uncovered lesson, or the reuse shortcut for a parallel section. Copies
     * content + stages; coverage starts fresh.
     */
    public function duplicate(Request $request, DailyLessonPlan $dailyPlan): JsonResponse
    {
        $user = $request->user();
        $sourceWeek = $dailyPlan->weeklyPlan;
        $plan = $sourceWeek->plan;
        LessonPlanAccess::assertOwner($user, $plan);

        $data = $request->validate([
            'teaches_on' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'section_ids' => ['sometimes', 'array', 'max:20'],
            'section_ids.*' => ['integer'],
            'period_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            // One-tap bump: close the uncovered source sittings as missed.
            'mark_source_missed' => ['sometimes', 'boolean'],
        ]);

        $teachesOn = CarbonImmutable::parse($data['teaches_on']);
        $weekStart = $teachesOn->startOfWeek(CarbonImmutable::MONDAY);

        $sectionIds = collect($data['section_ids'] ?? $dailyPlan->deliveries->pluck('section_id')->all())->unique();
        $sections = $this->assertSections($plan, $sectionIds->all());

        $week = $plan->weeklyPlans()->where('week_starts_on', $weekStart->toDateString())->first();

        if ($week === null) {
            $term = WeeklyLessonPlanController::termFor($plan, $weekStart);
            TermGate::assertWritable($term);

            $week = $plan->weeklyPlans()->create([
                'school_id' => $plan->school_id,
                'branch_id' => $plan->branch_id,
                'term_id' => $term?->id,
                'week_starts_on' => $weekStart->toDateString(),
                'status' => LessonPlanStatus::Draft,
            ]);
        } else {
            abort_unless($week->status->isEditable(), 422, 'The target week is awaiting review or approved — reopen it first.');
            TermGate::assertWritable($week->term);
        }

        $copy = DB::transaction(function () use ($dailyPlan, $week, $teachesOn, $sections, $data) {
            $copy = $week->dailyPlans()->create([
                'annual_plan_unit_id' => $dailyPlan->annual_plan_unit_id,
                'teaches_on' => $teachesOn->toDateString(),
                'topic' => $dailyPlan->topic,
                'subtopic' => $dailyPlan->subtopic,
                'rationale' => $dailyPlan->rationale,
                'prerequisite_knowledge' => $dailyPlan->prerequisite_knowledge,
                'objectives' => $dailyPlan->objectives,
                'support_slow' => $dailyPlan->support_slow,
                'support_medium' => $dailyPlan->support_medium,
                'support_fast' => $dailyPlan->support_fast,
                'homework' => $dailyPlan->homework,
                'sequence' => (int) $week->dailyPlans()->whereDate('teaches_on', $teachesOn->toDateString())->max('sequence') + 1,
            ]);

            foreach ($dailyPlan->stages as $stage) {
                $copy->stages()->create($stage->only([
                    'stage', 'learning_contents', 'page', 'teacher_activity',
                    'student_activity', 'assessment_techniques', 'teaching_aids', 'remark',
                ]));
            }

            foreach ($sections as $section) {
                $copy->deliveries()->create([
                    'section_id' => $section->id,
                    'teaches_on' => $teachesOn->toDateString(),
                    'period_number' => $data['period_number'] ?? null,
                    'coverage' => LessonCoverage::Pending,
                ]);
            }

            if ($data['mark_source_missed'] ?? false) {
                $dailyPlan->deliveries()
                    ->where('coverage', LessonCoverage::Pending->value)
                    ->update(['coverage' => LessonCoverage::Missed->value, 'coverage_note' => 'Moved to '.$teachesOn->toDateString().'.']);
            }

            return $copy;
        });

        return response()->json([
            'data' => $this->dayPayload($copy->fresh()->load($this->dayRelations()), $user),
            'message' => 'Lesson copied.',
        ], 201);
    }

    // ───────────────────────── internals ─────────────────────────

    /** @return list<string> */
    private function dayRelations(): array
    {
        return ['unit:id,title,sequence,page_from,page_to', 'stages', 'deliveries.section:id,name', 'weeklyPlan:id,annual_lesson_plan_id,week_starts_on,status'];
    }

    /** @return array<string, mixed> */
    private function validateDay(Request $request, AnnualLessonPlan $plan, bool $updating = false): array
    {
        $data = $request->validate([
            'teaches_on' => [$updating ? 'sometimes' : 'required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'topic' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'subtopic' => ['nullable', 'string', 'max:255'],
            'annual_plan_unit_id' => [
                'nullable', 'integer',
                Rule::exists('annual_plan_units', 'id')->where('annual_lesson_plan_id', $plan->id),
            ],
            'rationale' => ['nullable', 'string', 'max:5000'],
            'prerequisite_knowledge' => ['nullable', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'support_slow' => ['nullable', 'string', 'max:5000'],
            'support_medium' => ['nullable', 'string', 'max:5000'],
            'support_fast' => ['nullable', 'string', 'max:5000'],
            'homework' => ['nullable', 'string', 'max:2000'],
            'stages' => ['sometimes', 'array', 'max:3'],
            'stages.*.stage' => ['required', Rule::enum(LessonStage::class)],
            'stages.*.learning_contents' => ['nullable', 'string', 'max:5000'],
            'stages.*.page' => ['nullable', 'string', 'max:30'],
            'stages.*.teacher_activity' => ['nullable', 'string', 'max:5000'],
            'stages.*.student_activity' => ['nullable', 'string', 'max:5000'],
            'stages.*.assessment_techniques' => ['nullable', 'string', 'max:5000'],
            'stages.*.teaching_aids' => ['nullable', 'string', 'max:5000'],
            'stages.*.remark' => ['nullable', 'string', 'max:2000'],
            'deliveries' => [$updating ? 'sometimes' : 'required', 'array', 'min:1', 'max:20'],
            'deliveries.*.id' => ['sometimes', 'nullable', 'integer'],
            'deliveries.*.section_id' => ['required', 'integer'],
            'deliveries.*.teaches_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'deliveries.*.period_number' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if (array_key_exists('deliveries', $data)) {
            $this->assertSections($plan, collect($data['deliveries'])->pluck('section_id')->unique()->all());
        }

        return $data;
    }

    /**
     * Sections must be real classes of this plan: same branch, same grade.
     *
     * @param  list<int>  $sectionIds
     * @return Collection<int, Section>
     */
    private function assertSections(AnnualLessonPlan $plan, array $sectionIds)
    {
        $sections = Section::query()
            ->whereIn('id', $sectionIds)
            ->where('branch_id', $plan->branch_id)
            ->where('grade_level_id', $plan->grade_level_id)
            ->get(['id', 'name']);

        abort_unless($sections->count() === count($sectionIds), 422, 'Every section must belong to this plan\'s grade and branch.');

        return $sections;
    }

    /** @return array<string, mixed> */
    private function dayAttributes(array $data, bool $partial = false): array
    {
        $fields = [
            'annual_plan_unit_id', 'topic', 'subtopic', 'rationale', 'prerequisite_knowledge',
            'objectives', 'support_slow', 'support_medium', 'support_fast', 'homework',
        ];

        $out = [];
        foreach ($fields as $field) {
            if (! $partial || array_key_exists($field, $data)) {
                $out[$field] = $field === 'topic' || $field === 'annual_plan_unit_id'
                    ? ($data[$field] ?? null)
                    : $this->trimmedOrNull($data[$field] ?? null);
            }
        }

        if ($partial && ! array_key_exists('topic', $data)) {
            unset($out['topic']);
        }
        if ($partial && ! array_key_exists('annual_plan_unit_id', $data)) {
            unset($out['annual_plan_unit_id']);
        }

        return $out;
    }

    /**
     * Upsert the three stage rows; a stage whose every field is empty is
     * removed (the PDF simply skips the row).
     *
     * @param  list<array<string, mixed>>  $stages
     */
    private function syncStages(DailyLessonPlan $day, array $stages): void
    {
        foreach ($stages as $row) {
            $fields = [
                'learning_contents' => $this->trimmedOrNull($row['learning_contents'] ?? null),
                'page' => $this->trimmedOrNull($row['page'] ?? null),
                'teacher_activity' => $this->trimmedOrNull($row['teacher_activity'] ?? null),
                'student_activity' => $this->trimmedOrNull($row['student_activity'] ?? null),
                'assessment_techniques' => $this->trimmedOrNull($row['assessment_techniques'] ?? null),
                'teaching_aids' => $this->trimmedOrNull($row['teaching_aids'] ?? null),
                'remark' => $this->trimmedOrNull($row['remark'] ?? null),
            ];

            $stage = $row['stage'] instanceof LessonStage ? $row['stage']->value : (string) $row['stage'];

            if (collect($fields)->filter()->isEmpty()) {
                $day->stages()->where('stage', $stage)->delete();

                continue;
            }

            $day->stages()->updateOrCreate(['stage' => $stage], $fields);
        }
    }

    /**
     * Replace the sitting list: rows with a known id (or same section+period)
     * keep their coverage marks; the rest are inserted; missing ones go.
     *
     * @param  list<array<string, mixed>>  $deliveries
     */
    private function syncDeliveries(DailyLessonPlan $day, array $deliveries, CarbonImmutable $default): void
    {
        $existing = $day->deliveries()->get();
        $keep = [];

        foreach ($deliveries as $row) {
            $attributes = [
                'section_id' => (int) $row['section_id'],
                'teaches_on' => isset($row['teaches_on']) && $row['teaches_on'] !== null
                    ? CarbonImmutable::parse($row['teaches_on'])->toDateString()
                    : $default->toDateString(),
                'period_number' => $row['period_number'] ?? null,
            ];

            $match = null;
            if (isset($row['id']) && $row['id'] !== null) {
                $match = $existing->firstWhere('id', (int) $row['id']);
            }
            $match ??= $existing->first(fn ($d) => ! in_array($d->id, $keep, true)
                && (int) $d->section_id === $attributes['section_id']);

            if ($match !== null) {
                $match->update($attributes);
                $keep[] = $match->id;

                continue;
            }

            $keep[] = $day->deliveries()->create([...$attributes, 'coverage' => LessonCoverage::Pending])->id;
        }

        $day->deliveries()->whereNotIn('id', $keep)->delete();
    }

    private function trimmedOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function dayPayload(DailyLessonPlan $day, User $user): array
    {
        $week = $day->weeklyPlan;

        $stages = $day->stages
            ->sortBy(fn ($s) => $s->stage->sortOrder())
            ->map(fn ($s): array => [
                'stage' => $s->stage->value,
                'learning_contents' => $s->learning_contents,
                'page' => $s->page,
                'teacher_activity' => $s->teacher_activity,
                'student_activity' => $s->student_activity,
                'assessment_techniques' => $s->assessment_techniques,
                'teaching_aids' => $s->teaching_aids,
                'remark' => $s->remark,
            ])->values();

        return [
            'id' => $day->id,
            'weekly_lesson_plan_id' => $day->weekly_lesson_plan_id,
            'week_starts_on' => $week?->week_starts_on?->toDateString(),
            'week_status' => $week?->status?->value,
            'teaches_on' => $day->teaches_on->toDateString(),
            'topic' => $day->topic,
            'subtopic' => $day->subtopic,
            'unit_id' => $day->annual_plan_unit_id,
            'unit_title' => $day->relationLoaded('unit') ? $day->unit?->title : null,
            'rationale' => $day->rationale,
            'prerequisite_knowledge' => $day->prerequisite_knowledge,
            'objectives' => $day->objectives,
            'support_slow' => $day->support_slow,
            'support_medium' => $day->support_medium,
            'support_fast' => $day->support_fast,
            'homework' => $day->homework,
            'sequence' => $day->sequence,
            'stages' => $stages,
            'deliveries' => $day->deliveries->map(fn ($d): array => [
                'id' => $d->id,
                'section' => ['id' => $d->section?->id, 'name' => $d->section?->name],
                'teaches_on' => $d->teaches_on->toDateString(),
                'period_number' => $d->period_number,
                'coverage' => $d->coverage->value,
                'coverage_note' => $d->coverage_note,
            ])->values(),
            'is_own' => $week?->plan !== null && $week->plan->isOwnedBy($user),
            'can_review' => $week?->plan !== null && LessonPlanAccess::isReviewer($user, $week->plan),
            'editable' => $week !== null && $week->status->isEditable(),
        ];
    }
}
