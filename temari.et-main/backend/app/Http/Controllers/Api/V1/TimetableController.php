<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\TimetableVersionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateTimetableJob;
use App\Models\Employee;
use App\Models\Room;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Services\Timetable\ConstraintValidator;
use App\Services\Timetable\TimetableContext;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Timetable versions + their slot grid. The aSc workflow: create a draft →
 * generate (queued solver) → hand-tune with live hard-constraint validation
 * → lock what must stay → regenerate the rest → publish (archives the
 * previous published version). Published versions accept single-slot fixes —
 * validated, never silently conflicting.
 */
class TimetableController extends Controller
{
    // ───────────────────────── versions ─────────────────────────

    public function index(Request $request, Term $term): JsonResponse
    {
        $this->authorizeView($request, $term);

        $versions = $term->timetableVersions()
            ->with('term:id,name')
            ->latest()
            ->get();

        return response()->json([
            'data' => $versions->map(fn (TimetableVersion $v) => $this->versionRow($v)),
            // First-time setup state — lets the frontend open the guided
            // wizard (period schedule → rooms → first draft) in one request.
            'meta' => [
                'has_periods' => $term->periods()->exists(),
                'rooms_count' => Room::query()
                    ->where('branch_id', $term->branch_id)
                    ->where('is_active', true)
                    ->count(),
                // The generator only places assignments with a weekly load —
                // false here means the teaching grid still needs periods/week.
                'has_loads' => SubjectAssignment::query()
                    ->where('term_id', $term->id)
                    ->where('branch_id', $term->branch_id)
                    ->where('is_active', true)
                    ->where('periods_per_week', '>', 0)
                    ->exists(),
            ],
        ]);
    }

    public function store(Request $request, Term $term): JsonResponse
    {
        $this->authorizeManage($request, $term);
        TermGate::assertWritable($term);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'days' => ['sometimes', 'array', 'min:1', 'max:6'],
            'days.*' => ['integer', 'min:1', 'max:6'],
            'copy_from_id' => ['nullable', 'integer'],
        ]);

        abort_if($term->periods()->doesntExist(), 422, 'Set up the period schedule first.');

        $version = DB::transaction(function () use ($term, $data, $request): TimetableVersion {
            $version = $term->timetableVersions()->create([
                'school_id' => $term->school_id,
                'branch_id' => $term->branch_id,
                'name' => $data['name'],
                'status' => TimetableVersionStatus::Draft,
                'days' => array_values(array_unique($data['days'] ?? [1, 2, 3, 4, 5])),
                'created_by' => $request->user()->id,
            ]);

            if (! empty($data['copy_from_id'])) {
                $source = TimetableVersion::query()
                    ->where('term_id', $term->id)
                    ->findOrFail((int) $data['copy_from_id']);

                foreach ($source->slots()->get() as $slot) {
                    $version->slots()->create([
                        'subject_assignment_id' => $slot->subject_assignment_id,
                        'room_id' => $slot->room_id,
                        'day_of_week' => $slot->day_of_week,
                        'period_number' => $slot->period_number,
                        'is_locked' => $slot->is_locked,
                    ]);
                }
            }

            return $version;
        });

        return response()->json(['data' => $this->versionRow($version), 'message' => 'Draft created.'], 201);
    }

    /** Full grid payload for the editor. */
    public function show(Request $request, TimetableVersion $version): JsonResponse
    {
        $this->authorizeView($request, $version->term);

        $assignments = SubjectAssignment::query()
            ->where('term_id', $version->term_id)
            ->where('branch_id', $version->branch_id)
            ->where('is_active', true)
            ->with([
                'subject:id,code,name,weight,room_type',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'employee:id,first_name,father_name',
            ])
            ->get();

        $slots = $version->slots()->get();
        $placed = $slots->countBy('subject_assignment_id');

        return response()->json(['data' => [
            'version' => $this->versionRow($version),
            'periods' => $version->term->periods()->get()->map(fn ($p) => [
                'sequence' => $p->sequence,
                'type' => $p->type,
                'period_number' => $p->period_number,
                'label' => $p->label,
                'starts_at' => substr((string) $p->starts_at, 0, 5),
                'ends_at' => substr((string) $p->ends_at, 0, 5),
            ]),
            'sections' => $assignments->pluck('section')->unique('id')->sortBy([
                ['grade_level_id', 'asc'], ['name', 'asc'],
            ])->values()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'grade_level_id' => $s->grade_level_id,
                'grade_level_name' => $s->gradeLevel?->name,
            ]),
            'assignments' => $assignments->map(fn (SubjectAssignment $a) => [
                'id' => $a->id,
                'section_id' => $a->section_id,
                'subject' => [
                    'id' => $a->subject->id,
                    'code' => $a->subject->code,
                    'name' => $a->subject->name,
                    'weight' => $a->subject->weight,
                    'room_type' => $a->subject->room_type,
                ],
                'teacher_id' => $a->employee_id,
                'teacher_name' => $a->employee !== null
                    ? trim("{$a->employee->first_name} {$a->employee->father_name}")
                    : null,
                'periods_per_week' => $a->periods_per_week,
                'block_size' => $a->block_size,
                'placed' => $placed[$a->id] ?? 0,
            ])->values(),
            'slots' => $slots->map(fn (TimetableSlot $s) => $this->slotRow($s)),
            'rooms' => Room::query()
                ->where('branch_id', $version->branch_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ]]);
    }

    public function destroy(Request $request, TimetableVersion $version): JsonResponse
    {
        $this->authorizeManage($request, $version->term);
        abort_if($version->status === TimetableVersionStatus::Published, 422, 'Unpublish by publishing another version — the published timetable cannot be deleted.');

        $version->slots()->delete();
        $version->delete();

        return response()->json(['message' => 'Draft deleted.']);
    }

    /** Queue the solver. Locked slots stay; the rest regenerates. */
    public function generate(Request $request, TimetableVersion $version): JsonResponse
    {
        $this->authorizeManage($request, $version->term);
        TermGate::assertWritable($version->term);

        abort_unless($version->status === TimetableVersionStatus::Draft, 422, 'Only drafts can be generated.');
        abort_if($version->term->periods()->doesntExist(), 422, 'Set up the period schedule first.');

        // Without at least one assignment demanding periods the solver would
        // "succeed" with an empty grid — refuse with the actionable fix instead.
        $hasWork = SubjectAssignment::query()
            ->where('term_id', $version->term_id)
            ->where('branch_id', $version->branch_id)
            ->where('is_active', true)
            ->where('periods_per_week', '>', 0)
            ->exists();

        abort_unless($hasWork, 422, 'No subjects have weekly periods set for this semester — fill in periods per week on the subject assignments first.');

        $data = $request->validate([
            'teacher_daily_max' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $version->update(['status' => TimetableVersionStatus::Generating]);

        GenerateTimetableJob::dispatch($version->id, $data, $request->user()->id);

        return response()->json([
            'data' => $this->versionRow($version->refresh()),
            'message' => 'Generation started.',
        ]);
    }

    /** Publish: hard-constraint audit must be clean; the old published version archives. */
    public function publish(Request $request, TimetableVersion $version): JsonResponse
    {
        $this->authorizeManage($request, $version->term);
        TermGate::assertWritable($version->term);

        abort_unless($version->status === TimetableVersionStatus::Draft, 422, 'Only drafts can be published.');

        $problems = ConstraintValidator::for($version)->auditVersion();

        if ($problems !== []) {
            return response()->json([
                'message' => 'The timetable has conflicts that must be fixed before publishing.',
                'errors' => ['conflicts' => ['The timetable has unresolved conflicts.']],
                'conflicts' => $problems,
            ], 422);
        }

        DB::transaction(function () use ($version): void {
            TimetableVersion::query()
                ->where('term_id', $version->term_id)
                ->where('status', TimetableVersionStatus::Published->value)
                ->whereKeyNot($version->id)
                ->update(['status' => TimetableVersionStatus::Archived->value]);

            $version->update([
                'status' => TimetableVersionStatus::Published,
                'published_at' => now(),
            ]);
        });

        $this->notifyPublished($version);

        return response()->json([
            'data' => $this->versionRow($version->refresh()),
            'message' => 'Timetable published.',
        ]);
    }

    /**
     * A published week concerns everyone who lives by it: the branch's
     * teachers with term workloads, and every actively-enrolled student with
     * a login. In-app only, queued — this is the platform's biggest fan-out.
     */
    private function notifyPublished(TimetableVersion $version): void
    {
        $term = $version->term;

        if ($term === null) {
            return;
        }

        $notifier = app(Notifier::class);
        $data = ['term' => $term->name];
        $options = [
            'link' => '/me/timetable',
            'schoolId' => $term->school_id,
            'branchId' => $term->branch_id,
            'dedupeKey' => "timetable:{$term->id}",
        ];

        $teachers = User::query()
            ->whereHas('employee.subjectAssignments', fn ($q) => $q
                ->where('term_id', $term->id)->where('is_active', true))
            ->get();
        $notifier->toUsers($teachers, 'academics.timetable_published', $data, [...$options, 'link' => '/timetable']);

        $students = User::query()
            ->whereHas('studentProfile.enrollments', fn ($q) => $q
                ->where('branch_id', $term->branch_id)
                ->where('academic_year_id', $term->academic_year_id)
                ->where('status', EnrollmentStatus::Active->value))
            ->get();
        $notifier->toUsers($students, 'academics.timetable_published', $data, $options);
    }

    // ───────────────────────── slots ─────────────────────────

    public function storeSlot(Request $request, TimetableVersion $version): JsonResponse
    {
        $this->authorizeManage($request, $version->term);
        $this->assertSlotsEditable($version);

        $data = $request->validate([
            'subject_assignment_id' => ['required', 'integer', 'exists:subject_assignments,id'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:6'],
            'period_number' => ['required', 'integer', 'min:1', 'max:15'],
            'room_id' => ['nullable', 'integer'],
        ]);

        $assignment = SubjectAssignment::with(['subject:id,code,name,weight', 'section:id,name'])
            ->findOrFail((int) $data['subject_assignment_id']);
        abort_unless($assignment->term_id === $version->term_id, 422, 'The lesson belongs to a different semester.');
        $this->assertRoomBelongs($version, $data['room_id'] ?? null);

        $check = ConstraintValidator::for($version)->checkPlacement(
            $assignment,
            (int) $data['day_of_week'],
            (int) $data['period_number'],
            $data['room_id'] ?? null,
        );

        if ($check['violations'] !== []) {
            return $this->conflictResponse($check['violations']);
        }

        $slot = $version->slots()->create([
            'subject_assignment_id' => $assignment->id,
            'day_of_week' => (int) $data['day_of_week'],
            'period_number' => (int) $data['period_number'],
            'room_id' => $data['room_id'] ?? null,
        ]);

        return response()->json([
            'data' => $this->slotRow($slot),
            'meta' => ['warnings' => $check['warnings']],
            'message' => 'Lesson placed.',
        ], 201);
    }

    public function updateSlot(Request $request, TimetableVersion $version, TimetableSlot $slot): JsonResponse
    {
        $this->authorizeManage($request, $version->term);
        abort_unless($slot->timetable_version_id === $version->id, 404);

        $data = $request->validate([
            'day_of_week' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'period_number' => ['sometimes', 'integer', 'min:1', 'max:15'],
            'room_id' => ['sometimes', 'nullable', 'integer'],
            'is_locked' => ['sometimes', 'boolean'],
        ]);

        $moving = array_intersect_key($data, array_flip(['day_of_week', 'period_number', 'room_id'])) !== [];

        if ($moving) {
            $this->assertSlotsEditable($version);

            if (array_key_exists('room_id', $data)) {
                $this->assertRoomBelongs($version, $data['room_id']);
            }

            $assignment = $slot->subjectAssignment()->with(['subject:id,code,name,weight', 'section:id,name'])->first();

            $check = ConstraintValidator::for($version)->checkPlacement(
                $assignment,
                (int) ($data['day_of_week'] ?? $slot->day_of_week),
                (int) ($data['period_number'] ?? $slot->period_number),
                array_key_exists('room_id', $data) ? $data['room_id'] : $slot->room_id,
                ignoreSlotId: $slot->id,
            );

            if ($check['violations'] !== []) {
                return $this->conflictResponse($check['violations']);
            }
        }

        $slot->update($data);

        return response()->json([
            'data' => $this->slotRow($slot->refresh()),
            'meta' => ['warnings' => $moving ? ($check['warnings'] ?? []) : []],
            'message' => 'Slot updated.',
        ]);
    }

    public function destroySlot(Request $request, TimetableVersion $version, TimetableSlot $slot): JsonResponse
    {
        $this->authorizeManage($request, $version->term);
        abort_unless($slot->timetable_version_id === $version->id, 404);
        $this->assertSlotsEditable($version);

        $slot->delete();

        return response()->json(['message' => 'Lesson removed.']);
    }

    // ───────────────────────── read lanes ─────────────────────────

    /**
     * The signed-in teacher's own published timetable for a term (their
     * lessons across every section), resolved through their employee rows.
     */
    public function mine(Request $request): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $term = Term::findOrFail($request->integer('term_id'));
        $this->authorizeView($request, $term);

        $employeeIds = Employee::query()
            ->where('user_id', $request->user()->id)
            ->where('branch_id', $term->branch_id)
            ->pluck('id');

        $version = $term->timetableVersions()
            ->where('status', TimetableVersionStatus::Published->value)
            ->first();

        if ($version === null) {
            return response()->json(['data' => null, 'message' => 'No published timetable yet.']);
        }

        $slots = $version->slots()
            ->whereHas('subjectAssignment', fn ($q) => $q->whereIn('employee_id', $employeeIds))
            ->with(['subjectAssignment.subject:id,code,name', 'subjectAssignment.section:id,name,grade_level_id', 'subjectAssignment.section.gradeLevel:id,name', 'room:id,name'])
            ->get();

        return response()->json(['data' => [
            'version_id' => $version->id,
            'periods' => $term->periods()->get()->map(fn ($p) => [
                'sequence' => $p->sequence,
                'type' => $p->type,
                'period_number' => $p->period_number,
                'label' => $p->label,
                'starts_at' => substr((string) $p->starts_at, 0, 5),
                'ends_at' => substr((string) $p->ends_at, 0, 5),
            ]),
            'days' => $version->days,
            'slots' => $slots->map(fn (TimetableSlot $s) => [
                'day_of_week' => $s->day_of_week,
                'period_number' => $s->period_number,
                'subject' => $s->subjectAssignment->subject?->name,
                'section' => TimetableContext::sectionLabel($s->subjectAssignment->section),
                'room' => $s->room?->name,
            ]),
        ]]);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function authorizeView(Request $request, Term $term): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.view', $term->school_id, $term->branch_id),
            403,
        );
    }

    private function authorizeManage(Request $request, Term $term): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('timetable.manage', $term->school_id, $term->branch_id),
            403,
        );
    }

    private function assertSlotsEditable(TimetableVersion $version): void
    {
        TermGate::assertWritable($version->term);

        abort_unless(
            in_array($version->status, [TimetableVersionStatus::Draft, TimetableVersionStatus::Published], true),
            422,
            'This version is not editable right now.',
        );
    }

    private function assertRoomBelongs(TimetableVersion $version, ?int $roomId): void
    {
        if ($roomId === null) {
            return;
        }

        abort_unless(
            Room::query()->whereKey($roomId)->where('branch_id', $version->branch_id)->exists(),
            422,
            'That room belongs to a different branch.',
        );
    }

    /** @param  list<array<string, mixed>>  $violations */
    private function conflictResponse(array $violations): JsonResponse
    {
        return response()->json([
            'message' => 'That cell conflicts with the current timetable.',
            'errors' => [
                'slot' => ['That cell conflicts with the current timetable.'],
                // Stable codes the frontend translates into human phrasing.
                'conflicts' => array_values(array_unique(array_column($violations, 'code'))),
            ],
            'conflicts' => $violations,
        ], 422);
    }

    /** @return array<string, mixed> */
    private function versionRow(TimetableVersion $version): array
    {
        return [
            'id' => $version->id,
            'term_id' => $version->term_id,
            'name' => $version->name,
            'status' => $version->status->value,
            'status_label' => $version->status->label(),
            'score' => $version->score,
            'conflicts' => $version->conflicts,
            'days' => $version->days,
            'generated_at' => $version->generated_at?->toISOString(),
            'published_at' => $version->published_at?->toISOString(),
            'slots_count' => $version->slots()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function slotRow(TimetableSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'subject_assignment_id' => $slot->subject_assignment_id,
            'day_of_week' => $slot->day_of_week,
            'period_number' => $slot->period_number,
            'room_id' => $slot->room_id,
            'is_locked' => $slot->is_locked,
        ];
    }
}
