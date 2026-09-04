<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\MarklistStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MarklistResource;
use App\Models\AssessmentResult;
use App\Models\ContinuousAssessment;
use App\Models\Employee;
use App\Models\Marklist;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\ContinuousAssessmentMaterializer;
use App\Services\Notify\Notifier;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The marklist lane. Teachers open their own subject assignments
 * (grades.manage_own), fill marks and SUBMIT; supervisors (grades.approve)
 * approve or reopen — the Ethiopian teacher-signs / director-countersigns
 * ritual, digitised. Opening a marklist lazily creates its workflow row and
 * materialises the branch's grade book template, so the teacher always finds
 * the principal's plan waiting.
 */
class MarklistController extends Controller
{
    /**
     * Marklist register for the active term: the teacher's own assignments,
     * or every assignment in scope for supervisors — the approval queue.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $user = $request->user();
        $supervisor = $user->hasContextPermission('grades.manage') || $user->hasContextPermission('grades.approve');

        abort_unless($supervisor || $user->hasContextPermission('grades.manage_own'), 403);

        $branch = $this->activeBranchOrNull($request);

        $assignments = SubjectAssignment::query()
            ->where('term_id', $request->integer('term_id'))
            ->where('is_active', true)
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(
                $branch === null,
                fn ($q) => $q->where('school_id', $this->activeSchoolScopeId($request) ?? 0)
            )
            ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where('branch_id', $id))
            ->when(! $supervisor, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('user_id', $user->id)))
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->with([
                'subject:id,code,name',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name,sort_order',
                'employee:id,first_name,father_name,grandfather_name,user_id',
                'marklist.submitter:id,name',
                'marklist.approver:id,name',
                'marklist.assister:id,name',
            ])
            ->withCount('assessments')
            ->paginate(min($request->integer('per_page', 50), 100));

        $status = $request->string('status')->toString();

        $plannedCounts = $this->plannedAssessmentCounts(collect($assignments->items()));

        $data = collect($assignments->items())
            ->map(fn (SubjectAssignment $a): array => [
                'subject_assignment_id' => $a->id,
                'subject' => ['id' => $a->subject?->id, 'code' => $a->subject?->code, 'name' => $a->subject?->name],
                'section' => ['id' => $a->section?->id, 'name' => $a->section?->name, 'grade_level' => $a->section?->gradeLevel?->name],
                'teacher_name' => $a->employee?->full_name,
                'is_own' => $a->isOwnedBy($request->user()),
                // Plans materialise lazily on first open — the register still
                // shows the column count the teacher WILL find there.
                'assessments_count' => max((int) $a->assessments_count, $plannedCounts[$a->id] ?? 0),
                'marklist' => $a->marklist !== null ? new MarklistResource($a->marklist) : null,
            ])
            ->when($status !== '', fn ($rows) => $rows->filter(
                fn (array $row): bool => ($row['marklist']?->status?->value ?? 'draft') === $status
            )->values());

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    /**
     * The marks grid for one assignment: plan-materialised assessments,
     * section roster, every recorded score, and the workflow state.
     */
    public function show(Request $request, SubjectAssignment $subjectAssignment, ContinuousAssessmentMaterializer $materializer): JsonResponse
    {
        $this->authorizeView($request, $subjectAssignment);

        $book = $materializer->bookFor($subjectAssignment);

        if ($book !== null && ! $subjectAssignment->term?->isClosed()) {
            $materializer->materialize($subjectAssignment, $book);
        }

        $marklist = $this->marklistFor($subjectAssignment);

        $subjectAssignment->load([
            'subject:id,code,name',
            'section:id,name,grade_level_id',
            'section.gradeLevel:id,name,sort_order',
            'employee:id,first_name,father_name,grandfather_name,user_id',
            'term:id,name,status',
            'assessments' => fn ($q) => $q->orderByRaw('continuous_assessment_item_id IS NULL')->orderBy('id'),
            'assessments.results',
        ]);

        $students = $subjectAssignment->section->enrollments()
            ->where('status', EnrollmentStatus::Active->value)
            ->where('academic_year_id', $subjectAssignment->academic_year_id)
            ->with('student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path')
            ->get()
            ->sortBy(fn ($e) => $e->student?->full_name)
            ->values();

        $user = $request->user();
        $ownsIt = $subjectAssignment->isOwnedBy($user);
        $canManage = $user->hasPermissionForScope('grades.manage', $subjectAssignment->school_id, $subjectAssignment->branch_id);
        $canApprove = $user->hasPermissionForScope('grades.approve', $subjectAssignment->school_id, $subjectAssignment->branch_id);
        $vacant = $subjectAssignment->employee?->user_id === null;
        $assistedByMe = (int) ($marklist->assisted_by ?? 0) === (int) $user->id;
        $termClosed = $subjectAssignment->term?->isClosed() ?? false;

        // The trust rule, precomputed for the UI: a supervisor sees a
        // teacher-owned draft read-only until they declare on-behalf entry.
        $canEditMarks = ! $marklist->isLocked() && ! $termClosed && (
            ($ownsIt && ($canManage || $user->hasPermissionForScope('grades.manage_own', $subjectAssignment->school_id, $subjectAssignment->branch_id)))
            || ($canManage && ($vacant || $assistedByMe))
        );

        // Four-eyes: whoever put marks on the sheet (typed cells, declared
        // assistance, or signed the submission) cannot also countersign it.
        $fourEyesBlocked = $canApprove && (
            (int) ($marklist->submitted_by ?? 0) === (int) $user->id
            || $assistedByMe
            || $this->recordedCellsOn($user, $subjectAssignment)
        );

        return response()->json([
            'data' => [
                'subject_assignment_id' => $subjectAssignment->id,
                'subject' => [
                    'id' => $subjectAssignment->subject?->id,
                    'code' => $subjectAssignment->subject?->code,
                    'name' => $subjectAssignment->subject?->name,
                ],
                'section' => [
                    'id' => $subjectAssignment->section?->id,
                    'name' => $subjectAssignment->section?->name,
                    'grade_level' => $subjectAssignment->section?->gradeLevel?->name,
                ],
                'term' => [
                    'id' => $subjectAssignment->term?->id,
                    'name' => $subjectAssignment->term?->name,
                    'is_closed' => $subjectAssignment->term?->isClosed() ?? false,
                ],
                'teacher_name' => $subjectAssignment->employee?->full_name,
                'is_own' => $ownsIt,
                'can_approve' => $canApprove,
                'can_edit_marks' => $canEditMarks,
                'can_request_assist' => $canManage && ! $ownsIt && ! $vacant && ! $assistedByMe
                    && $marklist->status === MarklistStatus::Draft && ! $termClosed,
                'four_eyes_blocked' => $fourEyesBlocked,
                'can_define_assessments' => $this->canDefineAssessments($request, $subjectAssignment, $book !== null),
                'recorders' => $this->recordersFor($subjectAssignment),
                'continuous_assessment' => $book !== null ? ['id' => $book->id, 'name' => $book->name] : null,
                'marklist' => new MarklistResource($marklist->load(['submitter:id,name', 'approver:id,name', 'assister:id,name'])),
                'assessments' => $subjectAssignment->assessments->map(fn ($a): array => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'name' => $a->name,
                    'max_score' => (float) $a->max_score,
                    'weight' => (float) $a->weight,
                    'conducted_on' => $a->conducted_on?->toDateString(),
                    'is_planned' => $a->isPlanned(),
                ])->values(),
                'students' => $students->map(function ($enrollment) use ($subjectAssignment): array {
                    $student = $enrollment->student;

                    return [
                        'student_id' => $student->id,
                        'public_id' => $student->public_id,
                        'full_name' => $student->full_name,
                        'gender' => $student->gender,
                        'scores' => $subjectAssignment->assessments->map(function ($a) use ($student): array {
                            $result = $a->results->firstWhere('student_id', $student->id);

                            return [
                                'assessment_id' => $a->id,
                                'score' => $result?->score !== null ? (float) $result->score : null,
                                'is_absent' => $result?->is_absent ?? false,
                                'recorded_by' => $result?->recorded_by,
                            ];
                        })->values(),
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * @return array{subject: string, section: string}
     */
    private static function marklistVars(SubjectAssignment $subjectAssignment): array
    {
        $subjectAssignment->loadMissing(['subject:id,name', 'section:id,name']);

        return [
            'subject' => $subjectAssignment->subject?->name ?? '',
            'section' => $subjectAssignment->section?->name ?? '',
        ];
    }

    /** Teacher sign-off: draft → submitted. */
    public function submit(Request $request, SubjectAssignment $subjectAssignment): JsonResponse
    {
        $user = $request->user();

        $canManage = $user->hasPermissionForScope('grades.manage', $subjectAssignment->school_id, $subjectAssignment->branch_id);
        $ownsIt = $user->hasPermissionForScope('grades.manage_own', $subjectAssignment->school_id, $subjectAssignment->branch_id)
            && $subjectAssignment->isOwnedBy($user);

        abort_unless($canManage || $ownsIt, 403);
        TermGate::assertWritable($subjectAssignment->term);

        $marklist = $this->marklistFor($subjectAssignment);
        abort_unless($marklist->status === MarklistStatus::Draft, 422, 'Only a draft marklist can be submitted.');

        $marklist->update([
            'status' => MarklistStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $user->id,
            'remarks' => $request->string('remarks')->toString() ?: null,
        ]);

        app(Notifier::class)->toStaff(
            $subjectAssignment->school_id,
            $subjectAssignment->branch_id,
            'grades.approve',
            'academics.marklist_submitted',
            [
                'teacher' => $user->name,
                ...self::marklistVars($subjectAssignment),
            ],
            ['link' => '/marklists', 'exceptUserId' => $user->id],
        );

        Analytics::capture($user, 'marklist.submitted', [
            'marklist_id' => $marklist->id,
            ...self::marklistVars($subjectAssignment),
        ], $subjectAssignment->school_id, $subjectAssignment->branch_id);

        return (new MarklistResource($marklist->load(['submitter:id,name', 'approver:id,name'])))
            ->additional(['message' => 'Marklist submitted for approval.'])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * A supervisor declares on-behalf mark entry on a teacher-owned draft:
     * the ONLY way into someone else's sheet. Requires a reason, notifies
     * the teacher immediately, and badges the grid, the submission and the
     * approval queue — silent supervisor edits do not exist.
     */
    public function assist(Request $request, SubjectAssignment $subjectAssignment): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('grades.manage', $subjectAssignment->school_id, $subjectAssignment->branch_id),
            403,
        );
        TermGate::assertWritable($subjectAssignment->term);

        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        abort_if($subjectAssignment->isOwnedBy($user), 422, 'This is your own class — enter marks directly.');

        $subjectAssignment->loadMissing('employee');
        abort_if(
            $subjectAssignment->employee?->user_id === null,
            422,
            'This class has no teacher account — marks can be entered directly.',
        );

        $marklist = $this->marklistFor($subjectAssignment);
        abort_unless($marklist->status === MarklistStatus::Draft, 422, 'Only a draft marklist can be entered on behalf of its teacher.');

        $marklist->update([
            'assisted_by' => $user->id,
            'assisted_at' => now(),
            'assist_reason' => $request->string('reason')->toString(),
        ]);

        app(Notifier::class)->toUser(
            User::find($subjectAssignment->employee->user_id),
            'academics.marklist_assist',
            [
                'supervisor' => $user->name,
                'reason' => $request->string('reason')->toString(),
                ...self::marklistVars($subjectAssignment),
            ],
            [
                'link' => "/marklists/{$subjectAssignment->id}",
                'schoolId' => $subjectAssignment->school_id,
                'branchId' => $subjectAssignment->branch_id,
            ],
        );

        Analytics::capture($user, 'marklist.assist_started', [
            'marklist_id' => $marklist->id,
            ...self::marklistVars($subjectAssignment),
        ], $subjectAssignment->school_id, $subjectAssignment->branch_id);

        return (new MarklistResource($marklist->load('assister:id,name')))
            ->additional(['message' => 'On-behalf entry started — the teacher has been notified.'])
            ->response()
            ->setStatusCode(200);
    }

    /** Supervisor countersign: submitted → approved. */
    public function approve(Request $request, SubjectAssignment $subjectAssignment): JsonResponse
    {
        $this->authorizeApprover($request, $subjectAssignment);

        $marklist = $this->marklistFor($subjectAssignment);
        abort_unless($marklist->status === MarklistStatus::Submitted, 422, 'Only a submitted marklist can be approved.');

        // Four-eyes: entering marks (or signing the submission) and
        // countersigning them are two different people, always.
        abort_if(
            (int) ($marklist->submitted_by ?? 0) === (int) $request->user()->id
            || (int) ($marklist->assisted_by ?? 0) === (int) $request->user()->id
            || $this->recordedCellsOn($request->user(), $subjectAssignment),
            422,
            'You entered marks on this marklist — another supervisor must approve it.',
        );

        $submitterId = $marklist->submitted_by;

        $marklist->update([
            'status' => MarklistStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        app(Notifier::class)->toUser(User::find($submitterId), 'academics.marklist_decided', [
            ...self::marklistVars($subjectAssignment),
            'status' => 'approved',
        ], [
            'link' => '/marklists',
            'schoolId' => $subjectAssignment->school_id,
            'branchId' => $subjectAssignment->branch_id,
            'exceptUserId' => $request->user()->id,
        ]);

        Analytics::capture($request->user(), 'marklist.approved', [
            'marklist_id' => $marklist->id,
            ...self::marklistVars($subjectAssignment),
        ], $subjectAssignment->school_id, $subjectAssignment->branch_id);

        return (new MarklistResource($marklist->load(['submitter:id,name', 'approver:id,name'])))
            ->additional(['message' => 'Marklist approved.'])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Back to draft for corrections. The submitting teacher may withdraw
     * while still awaiting approval; unlocking an APPROVED marklist takes
     * the approval authority.
     */
    public function reopen(Request $request, SubjectAssignment $subjectAssignment): JsonResponse
    {
        $user = $request->user();
        $marklist = $this->marklistFor($subjectAssignment);

        abort_unless($marklist->status !== MarklistStatus::Draft, 422, 'Marklist is already a draft.');

        $isApprover = $user->hasPermissionForScope('grades.approve', $subjectAssignment->school_id, $subjectAssignment->branch_id);
        $isWithdrawal = $marklist->status === MarklistStatus::Submitted
            && (int) $marklist->submitted_by === (int) $user->id;

        abort_unless($isApprover || $isWithdrawal, 403);
        TermGate::assertWritable($subjectAssignment->term);

        $submitterId = $marklist->submitted_by;

        $marklist->update([
            'status' => MarklistStatus::Draft,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'remarks' => $request->string('remarks')->toString() ?: null,
        ]);

        // An approver sending it back is a decision the teacher must hear
        // about; a teacher withdrawing their own submission is not.
        if ($isApprover && $submitterId !== null && (int) $submitterId !== (int) $user->id) {
            app(Notifier::class)->toUser(User::find($submitterId), 'academics.marklist_decided', [
                ...self::marklistVars($subjectAssignment),
                'status' => 'reopened',
            ], [
                'link' => '/marklists',
                'schoolId' => $subjectAssignment->school_id,
                'branchId' => $subjectAssignment->branch_id,
            ]);
        }

        Analytics::capture($user, 'marklist.reopened', [
            'marklist_id' => $marklist->id,
            'by_approver' => $isApprover,
            ...self::marklistVars($subjectAssignment),
        ], $subjectAssignment->school_id, $subjectAssignment->branch_id);

        return (new MarklistResource($marklist))
            ->additional(['message' => 'Marklist reopened for editing.'])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Whether the requester may shape this marklist's assessment STRUCTURE:
     * never under a plan; always for supervisors (grades.manage); for the
     * owning teacher only when the branch opted in (`teacher_assessments_enabled`).
     */
    private function canDefineAssessments(Request $request, SubjectAssignment $assignment, bool $hasPlan): bool
    {
        if ($hasPlan || ($assignment->term?->isClosed() ?? false)) {
            return false;
        }

        $user = $request->user();

        if ($user->hasPermissionForScope('grades.manage', $assignment->school_id, $assignment->branch_id)) {
            return true;
        }

        $assignment->loadMissing('branch.school');

        return $assignment->isOwnedBy($user)
            && ($assignment->branch?->effectiveTeacherAssessmentsEnabled() ?? false);
    }

    /**
     * Per-assignment count of grade-book plan items that WILL materialise on
     * first open — one bulk query, precedence resolved in PHP through the
     * shared ContinuousAssessmentMaterializer::governingBook (subject-specific
     * beats general, section-specific beats all-sections, newest wins ties).
     *
     * @param  Collection<int, SubjectAssignment>  $assignments
     * @return array<int, int>
     */
    private function plannedAssessmentCounts($assignments): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $plans = ContinuousAssessment::query()
            ->whereIn('branch_id', $assignments->pluck('branch_id')->unique())
            ->whereIn('term_id', $assignments->pluck('term_id')->unique())
            ->where('is_active', true)
            ->with('targets')
            ->withCount('items')
            ->get();

        $materializer = app(ContinuousAssessmentMaterializer::class);

        return $assignments->mapWithKeys(function (SubjectAssignment $a) use ($plans, $materializer): array {
            $book = $materializer->governingBook($a, $plans);

            return [$a->id => (int) ($book->items_count ?? 0)];
        })->all();
    }

    /**
     * Did this user personally record any score cell of the assignment?
     * `assessment_results.recorded_by` is the per-cell audit trail; a user
     * may hold several employee files (one per branch), so match them all.
     */
    private function recordedCellsOn(User $user, SubjectAssignment $assignment): bool
    {
        $employeeIds = Employee::query()->where('user_id', $user->id)->pluck('id');

        if ($employeeIds->isEmpty()) {
            return false;
        }

        return AssessmentResult::query()
            ->whereIn('assessment_id', $assignment->assessments()->select('id'))
            ->whereIn('recorded_by', $employeeIds)
            ->exists();
    }

    /**
     * Everyone who recorded at least one cell that is NOT the owning
     * teacher — powers the "entered by" badges. One aggregate query.
     *
     * @return list<array{employee_id: int, name: string, cells: int}>
     */
    private function recordersFor(SubjectAssignment $assignment): array
    {
        $counts = AssessmentResult::query()
            ->whereIn('assessment_id', $assignment->assessments()->select('id'))
            ->whereNotNull('recorded_by')
            ->when(
                $assignment->employee_id !== null,
                fn ($q) => $q->where('recorded_by', '!=', $assignment->employee_id)
            )
            ->groupBy('recorded_by')
            ->selectRaw('recorded_by, count(*) as cells')
            ->pluck('cells', 'recorded_by');

        if ($counts->isEmpty()) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $counts->keys())
            ->get(['id', 'first_name', 'father_name', 'grandfather_name'])
            ->map(fn (Employee $e): array => [
                'employee_id' => $e->id,
                'name' => $e->full_name,
                'cells' => (int) $counts[$e->id],
            ])
            ->values()
            ->all();
    }

    private function marklistFor(SubjectAssignment $assignment): Marklist
    {
        return Marklist::firstOrCreate(
            ['subject_assignment_id' => $assignment->id],
            [
                'school_id' => $assignment->school_id,
                'branch_id' => $assignment->branch_id,
                'term_id' => $assignment->term_id,
                'status' => MarklistStatus::Draft,
            ],
        );
    }

    private function authorizeView(Request $request, SubjectAssignment $assignment): void
    {
        $user = $request->user();

        // Supervisory read (director/registrar…) — or the ownership lane,
        // which needs BOTH the teacher permission and actually owning the
        // assignment (a departed teacher keeps no access).
        $supervisor = $user->hasPermissionForScope('grades.view', $assignment->school_id, $assignment->branch_id)
            && ! $user->hasPermissionForScope('grades.manage_own', $assignment->school_id, $assignment->branch_id);

        $owner = $user->hasPermissionForScope('grades.manage_own', $assignment->school_id, $assignment->branch_id)
            && $assignment->isOwnedBy($user);

        abort_unless(
            $user->hasPermissionForScope('grades.manage', $assignment->school_id, $assignment->branch_id)
            || $user->hasPermissionForScope('grades.approve', $assignment->school_id, $assignment->branch_id)
            || $supervisor
            || $owner,
            403,
        );
    }

    private function authorizeApprover(Request $request, SubjectAssignment $assignment): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('grades.approve', $assignment->school_id, $assignment->branch_id),
            403,
        );
    }
}
