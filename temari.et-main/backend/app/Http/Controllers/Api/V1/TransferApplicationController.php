<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\TransferApplicationStatus;
use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\StudentTransferRequest;
use App\Models\TransferApplication;
use App\Services\TransferNotifier;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The DESTINATION school's inbox of parent/student-initiated applications.
 * Accepting places the student into a year + grade and materializes the
 * standard student_transfer_requests row — the CURRENT school keeps the
 * final say. Until then only directory-level facts about the student are
 * exposed here. Gated on `transfers.manage` like the rest of the movement
 * surface.
 */
class TransferApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_unless($user->hasContextPermission('transfers.manage'), 403);
        abort_if($schoolId === null, 422, 'Select a school context first.');

        $rows = TransferApplication::query()
            ->where('to_school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where('to_branch_id', $branch->id))
            ->when($branch === null, fn ($q) => $q->when(
                $this->branchFilterId($request, null),
                fn ($inner, int $id) => $inner->where('to_branch_id', $id),
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->with([
                // Limited profile only — full records stay with the current
                // school until IT approves the materialized request.
                'student:id,first_name,father_name,grandfather_name,gender,public_id,photo_path',
                'fromSchool:id,name', 'fromBranch:id,name', 'toBranch:id,name',
                'fromEnrollment.gradeLevel:id,name',
                'applicant:id,name',
                'transferRequest:id,status',
            ])
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        $rows->getCollection()->transform(fn (TransferApplication $application): array => [
            'id' => $application->id,
            'student' => [
                'full_name' => $application->student?->full_name,
                'public_id' => $application->student?->public_id,
                'gender' => $application->student?->gender,
                'photo_url' => $application->student?->photo_url,
            ],
            'from_school' => $application->fromSchool?->name,
            'from_branch' => $application->fromBranch?->name,
            'to_branch' => $application->toBranch?->name,
            'to_branch_id' => $application->to_branch_id,
            'current_grade' => $application->fromEnrollment?->gradeLevel?->name,
            'applicant_name' => $application->applicant?->name,
            'reason' => $application->reason,
            'status' => $application->status->value,
            'decline_note' => $application->decline_note,
            'request_status' => $application->transferRequest?->status?->value,
            'created_at' => $application->created_at?->toISOString(),
        ]);

        return response()->json($rows);
    }

    /**
     * Accept: place the student into a year + grade at the destination branch
     * and mint the standard transfer request the current school will decide.
     */
    public function accept(Request $request, TransferApplication $application): JsonResponse
    {
        $data = $request->validate([
            'to_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'to_grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
        ]);

        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('transfers.manage', $application->to_school_id, $application->to_branch_id),
            403,
        );
        abort_unless($application->status === TransferApplicationStatus::Submitted, 422, 'Only submitted applications can be accepted.');

        $toYear = AcademicYear::findOrFail((int) $data['to_academic_year_id']);
        abort_if($toYear->branch_id !== $application->to_branch_id, 422, 'The academic year must belong to the receiving branch.');

        // The enrollment situation may have changed since the family applied.
        $fromEnrollment = $application->student->enrollments()
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->latest('academic_year_id')
            ->first();
        abort_if($fromEnrollment === null, 422, 'The student no longer has a live enrollment to transfer from.');

        $pending = StudentTransferRequest::query()
            ->where('student_id', $application->student_id)
            ->where('status', TransferRequestStatus::Requested->value)
            ->exists();
        abort_if($pending, 422, 'A pending transfer request already exists for this student.');

        $transfer = DB::transaction(function () use ($application, $fromEnrollment, $toYear, $data, $user): StudentTransferRequest {
            $transfer = StudentTransferRequest::create([
                'student_id' => $application->student_id,
                'from_enrollment_id' => $fromEnrollment->id,
                'from_school_id' => $fromEnrollment->school_id,
                'from_branch_id' => $fromEnrollment->branch_id,
                'to_school_id' => $application->to_school_id,
                'to_branch_id' => $application->to_branch_id,
                'to_academic_year_id' => $toYear->id,
                'to_grade_level_id' => (int) $data['to_grade_level_id'],
                'status' => TransferRequestStatus::Requested,
                'reason' => $application->reason,
                'requested_by' => $user->id,
            ]);

            $application->update([
                'status' => TransferApplicationStatus::Accepted->value,
                'transfer_request_id' => $transfer->id,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            ActivityLogger::log(
                actor: $user,
                action: 'transfer_application.accepted',
                subject: $application,
                properties: ['student_id' => $application->student_id, 'transfer_request_id' => $transfer->id],
                schoolId: $application->to_school_id,
                branchId: $application->to_branch_id,
            );

            return $transfer;
        });

        app(TransferNotifier::class)->applicationAccepted($application);

        return response()->json([
            'data' => ['transfer_request_id' => $transfer->id],
            'message' => 'Application accepted — the request now awaits the current school.',
        ]);
    }

    public function decline(Request $request, TransferApplication $application): JsonResponse
    {
        $data = $request->validate(['decline_note' => ['required', 'string', 'max:500']]);

        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('transfers.manage', $application->to_school_id, $application->to_branch_id),
            403,
        );
        abort_unless($application->status === TransferApplicationStatus::Submitted, 422, 'Only submitted applications can be declined.');

        $application->update([
            'status' => TransferApplicationStatus::Declined->value,
            'decline_note' => $data['decline_note'],
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        ActivityLogger::log(
            actor: $user,
            action: 'transfer_application.declined',
            subject: $application,
            properties: ['student_id' => $application->student_id],
            schoolId: $application->to_school_id,
            branchId: $application->to_branch_id,
        );

        app(TransferNotifier::class)->applicationDeclined($application);

        return response()->json(['message' => 'Application declined.']);
    }
}
