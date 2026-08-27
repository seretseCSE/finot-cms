<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegisterStudentAction;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentRequest;
use App\Http\Requests\Api\V1\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransferRequest;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Student::class);
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        // A branch's students = registered here (provenance) OR enrolled here —
        // a transfer student enrolled at this branch must show up even though
        // they were registered elsewhere (students are global persons, ADR-011).
        $query = $branch
            ? Student::query()
                ->where(function ($q) use ($branch): void {
                    $q->where('branch_id', $branch->id)
                        ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $branch->id));
                })
                ->with(['currentEnrollment.section', 'currentEnrollment.gradeLevel'])
            : Student::query()
                ->when($schoolScopeId, function ($q) use ($schoolScopeId): void {
                    $q->where(function ($inner) use ($schoolScopeId): void {
                        $inner->where('school_id', $schoolScopeId)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolScopeId));
                    });
                })
                ->when($this->branchFilterId($request, $branch), function ($q, int $id): void {
                    $q->where(function ($inner) use ($id): void {
                        $inner->where('branch_id', $id)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $id));
                    });
                })
                // Platform staff narrowing (school → branch cascade).
                ->when(! $schoolScopeId && $request->filled('school_id'), function ($q) use ($request): void {
                    $id = $request->integer('school_id');
                    $q->where(function ($inner) use ($id): void {
                        $inner->where('school_id', $id)
                            ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $id));
                    });
                })
                ->with(['currentEnrollment.section', 'currentEnrollment.gradeLevel', 'branch.school']);

        $this->applyFilters($query, $request);

        // Soft-deleted rows are a platform-staff concern only.
        if ($request->user()->hasPlatformPermission('platform.access')) {
            $this->applyTrashedFilter($query, $request);
        }

        $this->applySort($query, $request, ['first_name', 'created_at', 'date_of_birth', 'public_id', 'gender'], 'created_at');

        $page = $query->paginate($this->perPage($request));

        $this->maskForeignEnrollments($page->getCollection(), $branch?->school_id ?? $schoolScopeId);

        return StudentResource::collection($page);
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Student::class);
        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = Student::query()
            ->when($branch, function ($q) use ($branch): void {
                $q->where(function ($inner) use ($branch): void {
                    $inner->where('branch_id', $branch->id)
                        ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $branch->id));
                });
            })
            ->when(! $branch && $schoolScopeId, function ($q) use ($schoolScopeId): void {
                $q->where(function ($inner) use ($schoolScopeId): void {
                    $inner->where('school_id', $schoolScopeId)
                        ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolScopeId));
                });
            })
            ->when($this->branchFilterId($request, $branch), function ($q, int $id): void {
                $q->where(function ($inner) use ($id): void {
                    $inner->where('branch_id', $id)
                        ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $id));
                });
            })
            ->with(['currentEnrollment.section', 'currentEnrollment.gradeLevel', 'branch.school']);

        $this->applyFilters($query, $request);

        $students = $query->orderBy('first_name')->limit(5000)->get();

        $this->maskForeignEnrollments($students, $branch?->school_id ?? $schoolScopeId);

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request, RegisterStudentAction $action): JsonResponse
    {
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('students.create', $branch->school_id, $branch->id),
            403,
        );

        // Filing a standing concession alongside the registration is a money
        // decision — it needs the fees authority, not just registrar rights.
        if ($request->filled('concession')) {
            abort_unless(
                $request->user()->hasPermissionForScope('fees.manage', $branch->school_id, $branch->id),
                403,
            );
        }

        $student = $action->execute($branch, $request->validated(), $request->user()->id);

        return (new StudentResource($student))
            ->additional(['message' => 'Student registered.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Student $student): StudentResource
    {
        $this->authorize('view', $student);

        // Former schools get the ARCHIVE lane: identity + their own era only.
        // Live data (current enrollment elsewhere, documents, health, account)
        // never flows backward after a transfer — mirrors PostTransferAccessTest.
        $archiveOnly = $student->isArchiveOnlyFor($request->user());

        $student->load([
            'enrollments.section.homerooms.employee:id,first_name,father_name,grandfather_name,phone,user_id',
            'enrollments.gradeLevel',
            'enrollments.academicYear',
            'enrollments.schoolProgram',
            'enrollments.previousSchool',
            'enrollments.branch.school:id,name',
        ]);

        if ($archiveOnly) {
            $student->setRelation('enrollments', $student->enrollments
                ->filter(fn ($enrollment): bool => $request->user()->hasPermissionForScope(
                    'students.view',
                    $enrollment->school_id,
                    $enrollment->branch_id,
                ))
                ->values());

            // The era snapshot: the file as the student LEFT this school —
            // address, health, and the documents that were on file then. New
            // material added by the receiving school never appears here.
            $snapshot = $student->archiveSnapshotFor($request->user());

            if ($snapshot !== null) {
                // withTrashed: a custody school "deleting" a snapshot-referenced
                // document only hides it from ITS live file — the former
                // school's frozen copy stays openable (retention, ADR-017).
                $eraIds = array_column($snapshot['attachments'] ?? [], 'id');
                $student->setRelation('attachments', $student->attachments()
                    ->withTrashed()
                    ->whereIn('id', $eraIds)
                    ->with(['branch:id,name', 'uploader:id,name'])
                    ->get());

                $student->setAttribute('archive_payload', [
                    'captured_at' => $snapshot['captured_at'] ?? null,
                    'profile' => $snapshot['profile'] ?? null,
                    'health' => $snapshot['health'] ?? null,
                ]);
            }
        } else {
            $student->load([
                'currentEnrollment.section.homerooms.employee:id,first_name,father_name,grandfather_name,phone,user_id',
                'currentEnrollment.gradeLevel',
                // password included (hidden by the model) so the resource can
                // derive has_password — "invited" vs "active" on the chip.
                'user:id,status,last_login_at,password,phone',
            ]);

            // Health data and documents are SENSITIVE: they load only for staff
            // who manage the record (registrar/director/principal), never for
            // read-only students.view holders (finance officers, support).
            if ($request->user()->can('update', $student)) {
                $student->load([
                    'healthConditions',
                    'attachments.branch:id,name',
                    'attachments.uploader:id,name',
                ]);
            }
        }

        $student->setAttribute('viewer_access', $archiveOnly ? 'archive' : 'full');
        $this->attachTransferFiles($request, $student);

        return new StudentResource($student);
    }

    /**
     * Transfer supporting documents live on the student record for the two
     * PARTICIPANT schools of each request (sender + receiver, any status) —
     * never for unrelated or future schools. Platform staff see all.
     */
    private function attachTransferFiles(Request $request, Student $student): void
    {
        $user = $request->user();

        $query = StudentTransferRequest::query()
            ->where('student_id', $student->id)
            ->whereHas('attachments')
            ->with(['attachments', 'fromSchool:id,name', 'toSchool:id,name'])
            ->latest();

        if (! $user->isSuperAdmin() && ! $user->hasPlatformPermission('students.view')) {
            $schoolIds = collect($student->adminScopes())
                ->filter(fn (array $scope): bool => $user->hasPermissionForScope('students.view', $scope[0], $scope[1]))
                ->pluck(0)
                ->filter()
                ->unique()
                ->values();

            if ($schoolIds->isEmpty()) {
                return;
            }

            $query->where(fn ($q) => $q
                ->whereIn('from_school_id', $schoolIds)
                ->orWhereIn('to_school_id', $schoolIds));
        }

        $transfers = $query->get();

        if ($transfers->isEmpty()) {
            return;
        }

        $student->setAttribute('transfer_files_payload', $transfers->map(fn ($transfer): array => [
            'id' => $transfer->id,
            'status' => $transfer->status->value,
            'from_school_name' => $transfer->fromSchool?->name,
            'to_school_name' => $transfer->toSchool?->name,
            'created_at' => $transfer->created_at,
            'files' => $transfer->attachments->map(fn ($file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'url' => $file->url(),
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'created_at' => $file->created_at,
            ])->values()->all(),
        ])->values()->all());
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $this->authorize('update', $student);

        $data = $request->safe()->except(['fayda_id', 'health_conditions']);

        if ($request->filled('fayda_id')) {
            $data['fayda_hash'] = hash('sha256', $request->string('fayda_id')->toString());
        }

        // The login follows a corrected phone: when the student's account is
        // keyed by the OLD number, re-key it to the new one (a typo fix in the
        // profile must not strand the portal login on the wrong phone).
        if (array_key_exists('primary_phone', $data)
            && is_string($data['primary_phone']) && $data['primary_phone'] !== ''
            && $data['primary_phone'] !== $student->primary_phone
            && $student->user_id !== null
            && $student->user?->phone === $student->primary_phone) {
            $phoneTaken = User::withTrashed()
                ->where('phone', $data['primary_phone'])
                ->whereKeyNot($student->user_id)
                ->exists();

            if ($phoneTaken) {
                throw ValidationException::withMessages([
                    'primary_phone' => ['This phone number already belongs to another Temari.et account, so the student\'s login cannot follow it. Use a different number.'],
                ]);
            }

            $student->user->forceFill(['phone' => $data['primary_phone']])->save();
        }

        $student->update($data);

        if ($request->has('health_conditions')) {
            $student->healthConditions()->sync(
                RegisterStudentAction::healthConditionSync($request->validated('health_conditions') ?? []),
            );
        }

        return new StudentResource($student->load([
            'currentEnrollment.section', 'currentEnrollment.gradeLevel', 'healthConditions',
        ]));
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('delete', $student);

        $student->delete();

        return response()->json(['message' => 'Student deleted.']);
    }

    /**
     * Remove a selection of students from the register — cleaning up a bad
     * import, or a cohort that never showed up. Custody is re-checked per row
     * (ADR-017), so a student who has already moved to another school is
     * skipped rather than deleted from under their new school's feet.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $deleted = 0;
        $skipped = [];

        foreach ($this->bulkRows($data['ids'], Student::query(), $skipped) as $student) {
            if ($actor->cannot('delete', $student)) {
                $skipped[] = self::skipRow($student, $student->full_name, 'not_permitted');

                continue;
            }

            $student->delete();
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} student(s) deleted.",
            'meta' => ['deleted' => $deleted, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /** Undo a deletion — the recovery path for an over-eager cleanup. */
    public function bulkRestore(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $restored = 0;
        $skipped = [];

        foreach ($this->bulkRows($data['ids'], Student::onlyTrashed(), $skipped) as $student) {
            if ($actor->cannot('delete', $student)) {
                $skipped[] = self::skipRow($student, $student->full_name, 'not_permitted');

                continue;
            }

            $student->restore();
            $restored++;
        }

        return response()->json([
            'message' => "{$restored} student(s) restored.",
            'meta' => ['restored' => $restored, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * A transferred-out student stays on their former school's register as a
     * read-only ARCHIVE row — but their live enrollment at the NEW school must
     * never leak backward. Rows whose current enrollment belongs to another
     * school swap it for the student's latest enrollment at the context school
     * (one batched query per page) and are flagged `access: archive`.
     *
     * @param  Collection<int, Student>  $students
     */
    private function maskForeignEnrollments(Collection $students, ?int $contextSchoolId): void
    {
        if ($contextSchoolId === null) {
            return; // Platform staff: full visibility, nothing to mask.
        }

        $foreign = $students->filter(fn (Student $s): bool => $s->relationLoaded('currentEnrollment')
            && $s->currentEnrollment !== null
            && (int) $s->currentEnrollment->school_id !== $contextSchoolId);

        if ($foreign->isEmpty()) {
            return;
        }

        $ownLatest = StudentEnrollment::query()
            ->whereIn('student_id', $foreign->pluck('id'))
            ->where('school_id', $contextSchoolId)
            ->with(['section', 'gradeLevel'])
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        foreach ($foreign as $student) {
            $student->setRelation('currentEnrollment', $ownLatest->get($student->id));
            $student->setAttribute('viewer_access', 'archive');
        }
    }

    /**
     * Search + list filters shared with export.
     *
     * @param  Builder<Student>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // `search_text` is the generated column holding every name part, the
        // phone (raw + digits-only), email and both ids behind one trigram
        // index — so a full name spans it where separate columns cannot.
        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('search_text', 'ilike', $this->needle($n)));

        if ($gradeIds = $this->csvIds($request, 'grade_level_id')) {
            $query->whereHas('currentEnrollment', fn ($e) => $e->whereIn('grade_level_id', $gradeIds));
        }

        if ($sectionIds = $this->csvIds($request, 'section_id')) {
            $query->whereHas('currentEnrollment', fn ($e) => $e->whereIn('section_id', $sectionIds));
        }

        if ($statuses = $this->csvValues($request, 'enrollment_status')) {
            $query->whereHas('enrollments', fn ($e) => $e->whereIn('status', $statuses));
        }

        if ($genders = $this->csvValues($request, 'gender')) {
            $query->whereIn('gender', $genders);
        }

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');
        $this->applyDateRange($query, $request, 'created_at', 'registered_from', 'registered_to');
    }
}
