<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\TransferApplicationStatus;
use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\StudentTransferRequest;
use App\Models\TransferApplication;
use App\Services\Notify\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transfers in the RELATIONSHIP LANE (ADR-012): families track every movement
 * of their children and file their own transfer applications online — the
 * NEMIS-style order (family → destination school accepts → current school
 * approves). Access derives from guardian links / the student's own account,
 * never from memberships.
 */
class MeTransferController extends Controller
{
    /**
     * Everything moving for this family: their applications (with the
     * materialized request's live status once accepted) plus school-initiated
     * transfer requests for their children.
     */
    public function index(Request $request): JsonResponse
    {
        $studentIds = $this->familyStudentIds($request);

        if ($studentIds === []) {
            return response()->json(['data' => ['applications' => [], 'requests' => []]]);
        }

        $applications = TransferApplication::query()
            ->whereIn('student_id', $studentIds)
            ->with([
                'student:id,first_name,father_name,grandfather_name',
                'fromSchool:id,name', 'fromBranch:id,name',
                'toSchool:id,name', 'toBranch:id,name',
                'transferRequest:id,status,decided_at',
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (TransferApplication $application): array => [
                'id' => $application->id,
                'student_id' => $application->student_id,
                'student_name' => $application->student?->full_name,
                'from_school' => $application->fromSchool?->name,
                'from_branch' => $application->fromBranch?->name,
                'to_school' => $application->toSchool?->name,
                'to_branch' => $application->toBranch?->name,
                'status' => $application->status->value,
                'reason' => $application->reason,
                'decline_note' => $application->decline_note,
                // Once accepted, tracking follows the materialized request.
                'request_status' => $application->transferRequest?->status?->value,
                'created_at' => $application->created_at?->toISOString(),
                'decided_at' => $application->decided_at?->toISOString(),
                'mine' => $application->applicant_user_id === $request->user()->id,
            ]);

        $requests = StudentTransferRequest::query()
            ->whereIn('student_id', $studentIds)
            ->whereNotIn('id', TransferApplication::query()
                ->whereIn('student_id', $studentIds)
                ->whereNotNull('transfer_request_id')
                ->select('transfer_request_id'))
            ->with([
                'student:id,first_name,father_name,grandfather_name',
                'fromSchool:id,name', 'fromBranch:id,name',
                'toSchool:id,name', 'toBranch:id,name',
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (StudentTransferRequest $transfer): array => [
                'id' => $transfer->id,
                'student_id' => $transfer->student_id,
                'student_name' => $transfer->student?->full_name,
                'from_school' => $transfer->fromSchool?->name,
                'from_branch' => $transfer->fromBranch?->name,
                'to_school' => $transfer->toSchool?->name,
                'to_branch' => $transfer->toBranch?->name,
                'status' => $transfer->status->value,
                'created_at' => $transfer->created_at?->toISOString(),
                'decided_at' => $transfer->decided_at?->toISOString(),
            ]);

        return response()->json(['data' => [
            'applications' => $applications,
            'requests' => $requests,
        ]]);
    }

    /**
     * Searchable destination catalog: ACTIVE Temari schools with their
     * branches — the family picks where they want to go. Names only; nothing
     * tenant-scoped leaks here.
     */
    public function destinations(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $schools = School::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($q, '\%_').'%'))
            ->with(['branches' => fn ($b) => $b->where('is_active', true)->select('id', 'school_id', 'name', 'city')])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(fn (School $school): array => [
                'id' => $school->id,
                'name' => $school->name,
                'branches' => $school->branches->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'city' => $branch->city,
                ])->values()->all(),
            ]);

        return response()->json(['data' => $schools]);
    }

    /** File a transfer application for a linked child (or oneself). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $student = Student::findOrFail((int) $data['student_id']);

        // Relationship gate: a linked active guardian, or the student themself.
        $parentId = $user->parentProfile()->value('id');
        $link = $parentId !== null
            ? StudentGuardian::query()
                ->where('parent_id', $parentId)
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->first()
            : null;
        $isSelf = $student->user_id === $user->id;

        abort_unless($link !== null || $isSelf, 403, 'This student is not linked to your account.');

        $toBranch = Branch::with('school:id,name,is_active')->findOrFail((int) $data['to_branch_id']);
        abort_unless((bool) $toBranch->is_active && (bool) $toBranch->school?->is_active, 422, 'That school is not accepting online applications.');

        $fromEnrollment = $student->enrollments()
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->latest('academic_year_id')
            ->first();

        abort_if($fromEnrollment === null, 422, 'The student has no live enrollment to transfer from.');
        abort_if($fromEnrollment->branch_id === $toBranch->id, 422, 'The student is already enrolled at this branch.');

        $liveApplication = TransferApplication::query()
            ->where('student_id', $student->id)
            ->where('status', TransferApplicationStatus::Submitted->value)
            ->exists();
        abort_if($liveApplication, 422, 'A pending application already exists for this student.');

        $pendingRequest = StudentTransferRequest::query()
            ->where('student_id', $student->id)
            ->where('status', TransferRequestStatus::Requested->value)
            ->exists();
        abort_if($pendingRequest, 422, 'A transfer request is already in progress for this student.');

        $application = TransferApplication::create([
            'student_id' => $student->id,
            'applicant_user_id' => $user->id,
            'applicant_parent_id' => $parentId,
            'from_enrollment_id' => $fromEnrollment->id,
            'from_school_id' => $fromEnrollment->school_id,
            'from_branch_id' => $fromEnrollment->branch_id,
            'to_school_id' => $toBranch->school_id,
            'to_branch_id' => $toBranch->id,
            'status' => TransferApplicationStatus::Submitted->value,
            'reason' => $data['reason'],
        ]);

        // The DESTINATION school's transfer desk reviews the application.
        app(Notifier::class)->toStaff(
            $toBranch->school_id,
            $toBranch->id,
            'transfers.manage',
            'movement.application_received',
            ['student' => $student->full_name],
            ['link' => '/transfers?tab=applications'],
        );

        return response()->json([
            'data' => ['id' => $application->id],
            'message' => 'Application submitted to the destination school.',
        ], 201);
    }

    /** The applicant withdraws their own application (before acceptance). */
    public function withdraw(Request $request, TransferApplication $application): JsonResponse
    {
        abort_unless($application->applicant_user_id === $request->user()->id, 403);
        abort_unless(
            $application->status === TransferApplicationStatus::Submitted,
            422,
            'Only a submitted application can be withdrawn.',
        );

        $application->update([
            'status' => TransferApplicationStatus::Withdrawn->value,
            'decided_at' => now(),
        ]);

        return response()->json(['message' => 'Application withdrawn.']);
    }

    /**
     * Every student this account speaks for: linked children + oneself.
     *
     * @return list<int>
     */
    private function familyStudentIds(Request $request): array
    {
        $user = $request->user();
        $parentId = $user->parentProfile()->value('id');

        $ids = StudentGuardian::query()
            ->where('is_active', true)
            ->where('parent_id', $parentId ?? 0)
            ->pluck('student_id');

        $own = Student::query()->where('user_id', $user->id)->value('id');

        return $ids->when($own !== null, fn ($c) => $c->push($own))->unique()->values()->all();
    }
}
