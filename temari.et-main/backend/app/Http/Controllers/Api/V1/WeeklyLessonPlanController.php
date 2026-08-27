<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LessonPlanStatus;
use App\Http\Controllers\Controller;
use App\Models\AnnualLessonPlan;
use App\Models\DailyLessonPlan;
use App\Models\Term;
use App\Models\User;
use App\Models\WeeklyLessonPlan;
use App\Services\LessonPlans\LessonPlanAccess;
use App\Services\LessonPlans\LessonPlanPacing;
use App\Services\Notify\Notifier;
use App\Support\TermGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The WEEKLY container lane. A week is the unit of submission, approval and
 * the pacing gate; the CONTENT lives in daily lesson plans (the MoE daily
 * format, DailyLessonPlanController) hanging off it. Submission enforces the
 * pacing rule: while last week has uncovered sittings the next week cannot
 * be filed WITHOUT a justification — with one, it goes through flagged, and
 * the reviewer decides. Approving the week approves its daily plans.
 */
class WeeklyLessonPlanController extends Controller
{
    /**
     * The composer's starting point for a week: the existing container if
     * one was already drafted, the annual units scheduled over that window,
     * the previous week's uncovered daily plans (carryover), and the gate.
     */
    public function prefill(Request $request, AnnualLessonPlan $lessonPlan, LessonPlanPacing $pacing): JsonResponse
    {
        LessonPlanAccess::assertViewer($request->user(), $lessonPlan);
        $request->validate(['week_starts_on' => ['required', 'date']]);

        $weekStart = LessonPlanPacing::weekStart($request->string('week_starts_on')->toString());
        $weekEnd = $weekStart->addDays(6);

        $existing = $lessonPlan->weeklyPlans()->where('week_starts_on', $weekStart->toDateString())->first();

        $units = $lessonPlan->units()
            ->whereNotNull('starts_on')
            ->whereDate('starts_on', '<=', $weekEnd)
            ->whereDate('ends_on', '>=', $weekStart)
            ->get()
            ->map(fn ($unit): array => [
                'id' => $unit->id,
                'sequence' => $unit->sequence,
                'title' => $unit->title,
                'objectives' => $unit->objectives,
                'planned_periods' => $unit->planned_periods,
                'page_from' => $unit->page_from,
                'page_to' => $unit->page_to,
            ]);

        $carryover = $pacing->carryover($lessonPlan, $weekStart);

        return response()->json([
            'data' => [
                'week_starts_on' => $weekStart->toDateString(),
                'existing_id' => $existing?->id,
                'units' => $units,
                'needs_justification' => $carryover['lessons']->isNotEmpty(),
                'carryover' => [
                    'week_starts_on' => $carryover['week']?->week_starts_on?->toDateString(),
                    'lessons' => $carryover['lessons']->map(fn (DailyLessonPlan $day): array => [
                        'id' => $day->id,
                        'topic' => $day->topic,
                        'teaches_on' => $day->teaches_on->toDateString(),
                        'unit_id' => $day->annual_plan_unit_id,
                        'unit_title' => $day->unit?->title,
                        'uncovered_sections' => $day->deliveries
                            ->filter(fn ($d) => ! $d->coverage->isCovered())
                            ->map(fn ($d) => $d->section?->name)
                            ->filter()->values(),
                    ]),
                ],
            ],
        ]);
    }

    /** Draft a week container (its daily plans are added one by one). */
    public function store(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        $user = $request->user();
        LessonPlanAccess::assertOwner($user, $lessonPlan);

        $data = $this->validateWeek($request);
        $weekStart = LessonPlanPacing::weekStart($data['week_starts_on']);

        $duplicate = $lessonPlan->weeklyPlans()->where('week_starts_on', $weekStart->toDateString())->exists();
        abort_if($duplicate, 422, 'A plan for this week already exists.');

        $term = self::termFor($lessonPlan, $weekStart);
        TermGate::assertWritable($term);

        $week = $lessonPlan->weeklyPlans()->create([
            'school_id' => $lessonPlan->school_id,
            'branch_id' => $lessonPlan->branch_id,
            'term_id' => $term?->id,
            'week_starts_on' => $weekStart->toDateString(),
            'status' => LessonPlanStatus::Draft,
            'notes' => $data['notes'] ?? null,
            'lag_justification' => $this->trimmedOrNull($data['lag_justification'] ?? null),
        ]);

        return response()->json(['data' => $this->weekPayload($week, $user), 'message' => 'Weekly plan drafted.'], 201);
    }

    /** All weeks of one annual plan, oldest first — the planner's timeline. */
    public function index(Request $request, AnnualLessonPlan $lessonPlan): JsonResponse
    {
        LessonPlanAccess::assertViewer($request->user(), $lessonPlan);

        $weeks = $lessonPlan->weeklyPlans()
            ->with(['dailyPlans.unit:id,title,sequence', 'dailyPlans.deliveries.section:id,name'])
            ->get();

        return response()->json([
            'data' => $weeks->map(fn (WeeklyLessonPlan $week): array => $this->weekPayload($week, $request->user(), loaded: true)),
        ]);
    }

    public function show(Request $request, WeeklyLessonPlan $weeklyPlan, LessonPlanPacing $pacing): JsonResponse
    {
        $plan = $weeklyPlan->plan;
        LessonPlanAccess::assertViewer($request->user(), $plan);

        $weeklyPlan->load([
            'dailyPlans.unit:id,title,sequence',
            'dailyPlans.deliveries.section:id,name',
            'submitter:id,name',
            'decider:id,name',
        ]);
        $plan->load(['subject:id,code,name', 'gradeLevel:id,name,sort_order', 'employee:id,first_name,father_name,grandfather_name,user_id']);

        $carryover = $pacing->carryover($plan, CarbonImmutable::parse($weeklyPlan->week_starts_on));

        return response()->json([
            'data' => [
                ...$this->weekPayload($weeklyPlan, $request->user(), loaded: true),
                'plan' => [
                    'id' => $plan->id,
                    'status' => $plan->status->value,
                    'subject' => ['id' => $plan->subject?->id, 'code' => $plan->subject?->code, 'name' => $plan->subject?->name],
                    'grade_level' => ['id' => $plan->gradeLevel?->id, 'name' => $plan->gradeLevel?->name],
                    'teacher_name' => $plan->employee?->full_name,
                ],
                'needs_justification' => $carryover['lessons']->isNotEmpty(),
                'carryover_topics' => $carryover['lessons']->pluck('topic'),
            ],
        ]);
    }

    /** Edit a draft/declined week container: notes + justification. */
    public function update(Request $request, WeeklyLessonPlan $weeklyPlan): JsonResponse
    {
        $user = $request->user();
        $plan = $weeklyPlan->plan;
        LessonPlanAccess::assertOwner($user, $plan);
        abort_unless($weeklyPlan->status->isEditable(), 422, 'This week is awaiting review or already approved — reopen it first.');
        TermGate::assertWritable($weeklyPlan->term);

        $data = $this->validateWeek($request, updating: true);

        $weeklyPlan->update([
            'notes' => array_key_exists('notes', $data) ? $this->trimmedOrNull($data['notes']) : $weeklyPlan->notes,
            'lag_justification' => array_key_exists('lag_justification', $data)
                ? $this->trimmedOrNull($data['lag_justification'])
                : $weeklyPlan->lag_justification,
        ]);

        return response()->json([
            'data' => $this->weekPayload($weeklyPlan->fresh()->load(['dailyPlans.unit:id,title,sequence', 'dailyPlans.deliveries.section:id,name']), $user, loaded: true),
            'message' => 'Weekly plan updated.',
        ]);
    }

    public function destroy(Request $request, WeeklyLessonPlan $weeklyPlan): JsonResponse
    {
        LessonPlanAccess::assertOwner($request->user(), $weeklyPlan->plan);
        abort_unless($weeklyPlan->status->isEditable(), 422, 'Only a draft or declined week can be deleted.');

        $weeklyPlan->delete();

        return response()->json(['message' => 'Weekly plan deleted.']);
    }

    /**
     * Teacher sign-off — where the pacing rule bites. Requires an APPROVED
     * annual plan; and while the previous week has uncovered sittings the
     * submission is refused unless a lag justification is attached.
     */
    public function submit(Request $request, WeeklyLessonPlan $weeklyPlan, LessonPlanPacing $pacing): JsonResponse
    {
        $user = $request->user();
        $plan = $weeklyPlan->plan;
        LessonPlanAccess::assertOwner($user, $plan);
        abort_unless($weeklyPlan->status->isEditable(), 422, 'Only a draft or declined week can be submitted.');
        TermGate::assertWritable($weeklyPlan->term);

        abort_unless(
            $plan->status === LessonPlanStatus::Approved,
            422,
            'Weekly plans can only be submitted under an approved annual plan.',
        );

        abort_if($weeklyPlan->dailyPlans()->count() === 0, 422, 'Add at least one daily lesson before submitting.');

        if ($request->filled('lag_justification')) {
            $request->validate(['lag_justification' => ['string', 'max:2000']]);
            $weeklyPlan->lag_justification = $this->trimmedOrNull($request->string('lag_justification')->toString());
        }

        $carryover = $pacing->carryover($plan, CarbonImmutable::parse($weeklyPlan->week_starts_on));

        if ($carryover['lessons']->isNotEmpty() && $weeklyPlan->lag_justification === null) {
            throw ValidationException::withMessages([
                'lag_justification' => ['Last week still has uncovered lessons — explain the delay to submit this week.'],
            ]);
        }

        $weeklyPlan->fill([
            'status' => LessonPlanStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $user->id,
            'decided_at' => null,
            'decided_by' => null,
            'decline_reason' => null,
        ])->save();

        app(Notifier::class)->toStaff(
            $plan->school_id,
            $plan->branch_id,
            'lesson_plans.review',
            'academics.weekly_plan_submitted',
            [
                'teacher' => $user->name,
                'week' => $weeklyPlan->week_starts_on->toDateString(),
                ...$this->planVars($plan),
            ],
            ['link' => '/lesson-plans?tab=review', 'exceptUserId' => $user->id],
        );

        return response()->json(['data' => $this->weekPayload($weeklyPlan, $user), 'message' => 'Weekly plan submitted for review.']);
    }

    public function approve(Request $request, WeeklyLessonPlan $weeklyPlan): JsonResponse
    {
        return $this->decide($request, $weeklyPlan, approve: true);
    }

    public function decline(Request $request, WeeklyLessonPlan $weeklyPlan): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->decide($request, $weeklyPlan, approve: false);
    }

    /** Owner withdraws a pending submission; a reviewer can unlock any state. */
    public function reopen(Request $request, WeeklyLessonPlan $weeklyPlan): JsonResponse
    {
        $user = $request->user();
        $plan = $weeklyPlan->plan;

        $isReviewer = LessonPlanAccess::isReviewer($user, $plan);
        $isWithdrawal = $weeklyPlan->status === LessonPlanStatus::Submitted
            && (int) $weeklyPlan->submitted_by === (int) $user->id;

        abort_unless($isReviewer || $isWithdrawal, 403);
        abort_if($weeklyPlan->status === LessonPlanStatus::Draft, 422, 'The week is already a draft.');
        TermGate::assertWritable($weeklyPlan->term);

        $submitterId = $weeklyPlan->submitted_by;

        $weeklyPlan->update([
            'status' => LessonPlanStatus::Draft,
            'submitted_at' => null,
            'submitted_by' => null,
            'decided_at' => null,
            'decided_by' => null,
            'decline_reason' => null,
        ]);

        if ($isReviewer && $submitterId !== null && (int) $submitterId !== (int) $user->id) {
            $this->notifyTeacher($weeklyPlan, $submitterId, 'reopened');
        }

        return response()->json(['data' => $this->weekPayload($weeklyPlan, $user), 'message' => 'Weekly plan reopened for editing.']);
    }

    /** The week of a date for a plan — shared with the daily lane. */
    public static function termFor(AnnualLessonPlan $plan, CarbonImmutable $weekStart): ?Term
    {
        return Term::query()
            ->where('academic_year_id', $plan->academic_year_id)
            ->whereDate('starts_on', '<=', $weekStart->addDays(6)->toDateString())
            ->whereDate('ends_on', '>=', $weekStart->toDateString())
            ->orderBy('sequence')
            ->first();
    }

    // ───────────────────────── internals ─────────────────────────

    private function decide(Request $request, WeeklyLessonPlan $weeklyPlan, bool $approve): JsonResponse
    {
        $user = $request->user();
        $plan = $weeklyPlan->plan;
        LessonPlanAccess::assertReviewer($user, $plan);
        abort_unless($weeklyPlan->status === LessonPlanStatus::Submitted, 422, 'Only a submitted week can be decided.');

        $submitterId = $weeklyPlan->submitted_by;

        $weeklyPlan->update([
            'status' => $approve ? LessonPlanStatus::Approved : LessonPlanStatus::Declined,
            'decided_at' => now(),
            'decided_by' => $user->id,
            'decline_reason' => $approve ? null : $request->string('reason')->toString(),
        ]);

        if ($submitterId !== null && (int) $submitterId !== (int) $user->id) {
            $this->notifyTeacher($weeklyPlan, $submitterId, $approve ? 'approved' : 'declined');
        }

        return response()->json([
            'data' => $this->weekPayload($weeklyPlan, $user),
            'message' => $approve ? 'Weekly plan approved.' : 'Weekly plan declined.',
        ]);
    }

    private function notifyTeacher(WeeklyLessonPlan $weeklyPlan, int $submitterId, string $status): void
    {
        $plan = $weeklyPlan->plan;

        app(Notifier::class)->toUser(User::find($submitterId), 'academics.weekly_plan_decided', [
            ...$this->planVars($plan),
            'week' => $weeklyPlan->week_starts_on->toDateString(),
            'status' => $status,
        ], [
            'link' => '/lesson-plans/'.$plan->id.'?week='.$weeklyPlan->week_starts_on->toDateString(),
            'schoolId' => $plan->school_id,
            'branchId' => $plan->branch_id,
        ]);
    }

    /** @return array<string, mixed> */
    private function validateWeek(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'week_starts_on' => [$updating ? 'sometimes' : 'required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lag_justification' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function trimmedOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
    private function weekPayload(WeeklyLessonPlan $week, User $user, bool $loaded = false): array
    {
        return [
            'id' => $week->id,
            'annual_lesson_plan_id' => $week->annual_lesson_plan_id,
            'week_starts_on' => $week->week_starts_on->toDateString(),
            'status' => $week->status->value,
            'notes' => $week->notes,
            'lag_justification' => $week->lag_justification,
            'decline_reason' => $week->decline_reason,
            'submitted_at' => $week->submitted_at?->toISOString(),
            'submitted_by_name' => $week->relationLoaded('submitter') ? $week->submitter?->name : null,
            'decided_at' => $week->decided_at?->toISOString(),
            'decided_by_name' => $week->relationLoaded('decider') ? $week->decider?->name : null,
            'is_own' => $week->plan !== null && $week->plan->isOwnedBy($user),
            'can_review' => $week->plan !== null && LessonPlanAccess::isReviewer($user, $week->plan),
            'days' => $loaded ? $week->dailyPlans->map(fn (DailyLessonPlan $day): array => [
                'id' => $day->id,
                'teaches_on' => $day->teaches_on->toDateString(),
                'topic' => $day->topic,
                'subtopic' => $day->subtopic,
                'sequence' => $day->sequence,
                'unit_id' => $day->annual_plan_unit_id,
                'unit_title' => $day->relationLoaded('unit') ? $day->unit?->title : null,
                'homework' => $day->homework,
                'deliveries' => $day->deliveries->map(fn ($d): array => [
                    'id' => $d->id,
                    'section' => ['id' => $d->section?->id, 'name' => $d->section?->name],
                    'teaches_on' => $d->teaches_on->toDateString(),
                    'period_number' => $d->period_number,
                    'coverage' => $d->coverage->value,
                    'coverage_note' => $d->coverage_note,
                ])->values(),
            ])->values() : null,
        ];
    }
}
