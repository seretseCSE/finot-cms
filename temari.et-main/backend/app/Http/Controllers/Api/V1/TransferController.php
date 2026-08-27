<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApproveTransferAction;
use App\Enums\EnrollmentStatus;
use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentTransferRequestResource;
use App\Models\AcademicYear;
use App\Models\GeneratedDocument;
use App\Models\Student;
use App\Models\StudentTransferRequest;
use App\Services\Notify\Notifier;
use App\Services\TransferNotifier;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * In-platform transfers. The receiving branch REQUESTS (after an exact-
 * identifier candidate lookup — never a cross-school name search), the
 * sending branch DECIDES. All actions gate on `transfers.manage` in the
 * side's own scope; cross-tenant visibility stays directory-level until
 * approval.
 */
class TransferController extends Controller
{
    use HandlesBulkActions;

    private const RELATIONS = [
        'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
        'fromSchool:id,name', 'fromBranch:id,name', 'toSchool:id,name', 'toBranch:id,name',
        'toAcademicYear:id,name', 'toGradeLevel:id,name',
        'fromEnrollment.gradeLevel:id,name', 'fromEnrollment.section:id,name', 'fromEnrollment.academicYear:id,name',
        'requester:id,name', 'decider:id,name', 'attachments',
    ];

    /**
     * Incoming (they want one of our students) and outgoing (we requested a
     * student) requests for every branch the user manages in context.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $user->activeSchoolId();

        abort_unless($user->hasContextPermission('transfers.manage'), 403);

        $direction = $request->string('direction')->value() ?: 'all';

        $query = StudentTransferRequest::query()
            ->with(self::RELATIONS)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest();

        // School-wide workspace: an explicit branch_id narrows OUR side of
        // each request to one branch (the school guard stays on).
        $filterBranchId = $this->branchFilterId($request, $branch);

        $scopeColumnPair = function ($q, string $side) use ($branch, $schoolId, $filterBranchId): void {
            if ($branch !== null) {
                $q->where("{$side}_branch_id", $branch->id);
            } else {
                $q->where("{$side}_school_id", $schoolId);
                if ($filterBranchId !== null) {
                    $q->where("{$side}_branch_id", $filterBranchId);
                }
            }
        };

        if ($direction === 'incoming') {
            // Requests asking to take students FROM us.
            $query->where(fn ($q) => $scopeColumnPair($q, 'from'));
        } elseif ($direction === 'outgoing') {
            $query->where(fn ($q) => $scopeColumnPair($q, 'to'));
        } else {
            $query->where(fn ($q) => $q
                ->where(fn ($inner) => $scopeColumnPair($inner, 'from'))
                ->orWhere(fn ($inner) => $scopeColumnPair($inner, 'to')));
        }

        return StudentTransferRequestResource::collection(
            $query->paginate(min($request->integer('per_page', 25), 100)),
        );
    }

    /**
     * Exact-identifier lookup of a transfer candidate (public ID or national
     * student ID). Returns directory-level facts only — the receiving school
     * learns nothing more until the sending school approves.
     */
    public function candidate(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('transfers.manage'), 403);

        $data = $request->validate(['query' => ['required', 'string', 'max:30']]);
        $needle = trim($data['query']);

        $student = Student::query()
            ->where(fn ($q) => $q
                ->where('public_id', strtoupper($needle))
                ->orWhere('national_student_id', $needle))
            ->first();

        $enrollment = $student?->enrollments()
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->with(['branch:id,name,school_id', 'branch.school:id,name', 'gradeLevel:id,name', 'academicYear:id,name'])
            ->latest('academic_year_id')
            ->first();

        if ($student === null || $enrollment === null) {
            return response()->json(['data' => null, 'message' => 'No enrolled student matches that ID.']);
        }

        return response()->json(['data' => [
            'student_id' => $student->id,
            'public_id' => $student->public_id,
            'full_name' => $student->full_name,
            'gender' => $student->gender,
            'photo_url' => $student->photo_url,
            'enrollment_id' => $enrollment->id,
            'school_name' => $enrollment->branch->school->name,
            'branch_name' => $enrollment->branch->name,
            'branch_id' => $enrollment->branch_id,
            'grade_level_name' => $enrollment->gradeLevel->name,
            'academic_year_name' => $enrollment->academicYear->name,
            'same_branch' => false,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'to_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'to_grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'reason' => ['required', 'string', 'max:500'],
            'branch_id' => ['sometimes', 'integer'],
            // Supporting documents (report card, fee clearance, parent
            // letter…), each with an optional display name chosen at upload.
            'documents' => ['sometimes', 'array', 'max:5'],
            'documents.*.file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
            'documents.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $toBranch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless($user->hasPermissionForScope('transfers.manage', $toBranch->school_id, $toBranch->id), 403);

        $toYear = AcademicYear::findOrFail($data['to_academic_year_id']);

        if ($toYear->branch_id !== $toBranch->id) {
            abort(422, 'The academic year must belong to the receiving branch.');
        }

        $student = Student::findOrFail($data['student_id']);

        $fromEnrollment = $student->enrollments()
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->latest('academic_year_id')
            ->first();

        abort_if($fromEnrollment === null, 422, 'This student has no live enrollment to transfer from.');
        abort_if($fromEnrollment->branch_id === $toBranch->id, 422, 'The student is already enrolled at this branch.');

        $exists = StudentTransferRequest::query()
            ->where('student_id', $student->id)
            ->where('status', TransferRequestStatus::Requested->value)
            ->exists();
        abort_if($exists, 422, 'A pending transfer request already exists for this student.');

        $transfer = StudentTransferRequest::create([
            'student_id' => $student->id,
            'from_enrollment_id' => $fromEnrollment->id,
            'from_school_id' => $fromEnrollment->school_id,
            'from_branch_id' => $fromEnrollment->branch_id,
            'to_school_id' => $toBranch->school_id,
            'to_branch_id' => $toBranch->id,
            'to_academic_year_id' => $toYear->id,
            'to_grade_level_id' => (int) $data['to_grade_level_id'],
            'status' => TransferRequestStatus::Requested,
            'reason' => $data['reason'],
            'requested_by' => $user->id,
        ]);

        // Supporting documents — the sending school reviews these before it
        // decides. Stored privately; both sides see signed URLs only.
        foreach ($request->file('documents', []) as $index => $document) {
            $file = $document['file'];
            $path = $file->store(
                "transfer-attachments/{$transfer->id}",
                ['disk' => config('filesystems.default')],
            );

            $transfer->attachments()->create([
                'name' => trim((string) $request->input("documents.{$index}.name")) ?: $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        ActivityLogger::log(
            actor: $user,
            action: 'transfer.requested',
            subject: $transfer,
            properties: ['student_id' => $student->id],
            schoolId: $toBranch->school_id,
            branchId: $toBranch->id,
        );

        // The family hears about every movement attempt immediately.
        app(TransferNotifier::class)->requested($transfer);

        // The SENDING school's transfer desk gets the approval request —
        // approving is the fee-clearance decision.
        app(Notifier::class)->toStaff(
            $transfer->from_school_id,
            $transfer->from_branch_id,
            'transfers.manage',
            'movement.transfer_action_needed',
            [
                'student' => $student->full_name,
                'to' => $toBranch->school?->name ?? '',
            ],
            ['link' => '/transfers', 'exceptUserId' => $user->id],
        );

        return (new StudentTransferRequestResource($transfer->load(self::RELATIONS)))
            ->additional(['message' => 'Transfer request sent to the current school.'])
            ->response()
            ->setStatusCode(201);
    }

    /** SENDING side: hand the student over. */
    public function approve(Request $request, StudentTransferRequest $transfer, ApproveTransferAction $action): StudentTransferRequestResource
    {
        abort_unless(
            $request->user()->hasPermissionForScope('transfers.manage', $transfer->from_school_id, $transfer->from_branch_id),
            403,
        );

        $action->execute($transfer, $request->user());

        app(TransferNotifier::class)->approved($transfer);

        return (new StudentTransferRequestResource($transfer->refresh()->load(self::RELATIONS)))
            ->additional(['message' => 'Transfer approved — the student now belongs to the receiving school.']);
    }

    /**
     * Decide a batch of incoming transfer requests — end of year, when a whole
     * cohort moves. The SENDING branch decides (approval is the fee-clearance
     * signal), so every row is permission-checked against its own from_branch.
     *
     * Approval runs the same action as the single-row path, one request per
     * call, so a student whose enrollment moved underneath us fails alone and is
     * reported — the rest of the batch still lands.
     */
    public function bulkDecide(Request $request, ApproveTransferAction $action): JsonResponse
    {
        $data = $request->validate([
            ...self::bulkIdRules(),
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => [Rule::requiredIf($request->input('decision') === 'rejected'), 'nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user();
        $approving = $data['decision'] === 'approved';
        $decided = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], StudentTransferRequest::with(self::RELATIONS), $skipped);

        foreach ($rows as $transfer) {
            $name = $transfer->student?->full_name;

            if (! $actor->hasPermissionForScope('transfers.manage', $transfer->from_school_id, $transfer->from_branch_id)) {
                $skipped[] = self::skipRow($transfer, $name, 'not_permitted');

                continue;
            }

            if ($transfer->status !== TransferRequestStatus::Requested) {
                $skipped[] = self::skipRow($transfer, $name, 'already_decided');

                continue;
            }

            if ($approving) {
                try {
                    $action->execute($transfer, $actor);
                } catch (ValidationException) {
                    // The student's enrollment moved since the request was filed.
                    $skipped[] = self::skipRow($transfer, $name, 'no_live_enrollment');

                    continue;
                }

                app(TransferNotifier::class)->approved($transfer);
            } else {
                $transfer->update([
                    'status' => TransferRequestStatus::Rejected,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'decision_note' => $data['decision_note'],
                ]);

                app(TransferNotifier::class)->rejected($transfer);
            }

            $decided++;
        }

        return response()->json([
            'message' => "{$decided} transfer request(s) decided.",
            'meta' => ['decided' => $decided, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /** SENDING side: refuse (e.g. outstanding fees). */
    public function reject(Request $request, StudentTransferRequest $transfer): StudentTransferRequestResource
    {
        abort_unless(
            $request->user()->hasPermissionForScope('transfers.manage', $transfer->from_school_id, $transfer->from_branch_id),
            403,
        );

        $data = $request->validate(['decision_note' => ['required', 'string', 'max:500']]);

        abort_unless($transfer->status === TransferRequestStatus::Requested, 422, 'Only pending requests can be rejected.');

        $transfer->update([
            'status' => TransferRequestStatus::Rejected,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'],
        ]);

        app(TransferNotifier::class)->rejected($transfer);

        return (new StudentTransferRequestResource($transfer->load(self::RELATIONS)))
            ->additional(['message' => 'Transfer request rejected.']);
    }

    /** RECEIVING side: withdraw its own request. */
    public function cancel(Request $request, StudentTransferRequest $transfer): StudentTransferRequestResource
    {
        abort_unless(
            $request->user()->hasPermissionForScope('transfers.manage', $transfer->to_school_id, $transfer->to_branch_id),
            403,
        );

        abort_unless($transfer->status === TransferRequestStatus::Requested, 422, 'Only pending requests can be cancelled.');

        $transfer->update([
            'status' => TransferRequestStatus::Cancelled,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        app(TransferNotifier::class)->cancelled($transfer);

        return (new StudentTransferRequestResource($transfer->load(self::RELATIONS)))
            ->additional(['message' => 'Transfer request cancelled.']);
    }

    /**
     * Printable transfer-letter payload — available to both sides once
     * approved. The frontend renders the A4 letter. First open mints the
     * public verification token the letter's QR code points at.
     */
    public function letter(Request $request, StudentTransferRequest $transfer): JsonResponse
    {
        $user = $request->user();

        $allowed = $user->hasPermissionForScope('transfers.manage', $transfer->from_school_id, $transfer->from_branch_id)
            || $user->hasPermissionForScope('transfers.manage', $transfer->to_school_id, $transfer->to_branch_id);
        abort_unless($allowed, 403);
        abort_unless($transfer->status === TransferRequestStatus::Approved, 422, 'The letter is available after approval.');

        if ($transfer->public_token === null) {
            $transfer->forceFill(['public_token' => Str::random(48)])->save();
        }

        return response()->json(['data' => self::letterPayload($transfer)]);
    }

    /**
     * UNAUTHENTICATED letter view behind the unguessable token — what the QR
     * code on the printed letter resolves to, so any school or parent can
     * verify the document against the platform.
     */
    public function publicLetter(string $token): JsonResponse
    {
        $transfer = StudentTransferRequest::query()
            ->where('public_token', $token)
            ->where('status', TransferRequestStatus::Approved->value)
            ->firstOrFail();

        // Printing goes through the OFFICIAL PDF (pre-warmed at approval),
        // never the browser's print of this web page.
        return response()->json(['data' => [
            ...self::letterPayload($transfer),
            ...GeneratedDocument::publicUrlsFor('transfer_letter', $transfer),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function letterPayload(StudentTransferRequest $transfer): array
    {
        $transfer->load(array_merge(self::RELATIONS, ['student']));
        $student = $transfer->student;

        return [
            'reference' => sprintf('TR-%05d', $transfer->id),
            'public_token' => $transfer->public_token,
            'student' => [
                'full_name' => $student->full_name,
                'public_id' => $student->public_id,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'photo_url' => $student->photo_url,
            ],
            'from_school' => $transfer->fromSchool->name,
            'from_branch' => $transfer->fromBranch->name,
            'to_school' => $transfer->toSchool->name,
            'to_branch' => $transfer->toBranch->name,
            'last_grade' => $transfer->fromEnrollment->gradeLevel?->name,
            'last_section' => $transfer->fromEnrollment->section?->name,
            'last_academic_year' => $transfer->fromEnrollment->academicYear?->name,
            'new_grade' => $transfer->toGradeLevel->name,
            'new_academic_year' => $transfer->toAcademicYear->name,
            'reason' => $transfer->reason,
            'approved_by' => $transfer->decider?->name,
            'approved_at' => $transfer->decided_at?->toDateString(),
        ];
    }
}
