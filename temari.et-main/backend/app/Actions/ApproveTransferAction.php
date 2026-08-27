<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Enums\PromotionDecision;
use App\Enums\TransferRequestStatus;
use App\Models\SchoolDirectoryEntry;
use App\Models\StudentPromotion;
use App\Models\StudentTransferRequest;
use App\Models\User;
use App\Services\StudentHandoverSnapshot;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The handover moment of an in-platform transfer, in ONE transaction: the old
 * enrollment closes (`transferred_out`), the new one opens at the receiving
 * branch (per its registration-fee gate), and the student_promotions audit
 * row links the two so academic history stays walkable across schools.
 * Approval authority — and therefore fee clearance — belongs to the SENDING
 * branch; this action assumes the caller already authorized that.
 */
class ApproveTransferAction
{
    public function __construct(private readonly EnrollStudentAction $enroll) {}

    public function execute(StudentTransferRequest $request, User $approver): StudentTransferRequest
    {
        return DB::transaction(function () use ($request, $approver): StudentTransferRequest {
            /** @var StudentTransferRequest $request */
            $request = StudentTransferRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== TransferRequestStatus::Requested) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending requests can be approved.'],
                ]);
            }

            $from = $request->fromEnrollment;

            if (! in_array($from->status, [EnrollmentStatus::Pending, EnrollmentStatus::Active], true)) {
                throw ValidationException::withMessages([
                    'status' => ['The student no longer has a live enrollment at the sending branch.'],
                ]);
            }

            // Freeze "the file as the student left" BEFORE anything changes —
            // the sending school's archive view reads this snapshot forever
            // (ADR-017): address, health, guardians, documents on file today.
            $snapshot = StudentHandoverSnapshot::capture($request->student);

            // Close the sending side first so the one-live-enrollment guard
            // never trips for same-year transfers.
            $from->update([
                'status' => EnrollmentStatus::TransferredOut,
                'exited_on' => now()->toDateString(),
            ]);

            $toEnrollment = $this->enroll->execute($request->student, [
                'academic_year_id' => $request->to_academic_year_id,
                'grade_level_id' => $request->to_grade_level_id,
                'previous_school_id' => SchoolDirectoryEntry::query()
                    ->where('school_id', $request->from_school_id)
                    ->value('id'),
            ]);

            StudentPromotion::updateOrCreate(
                ['from_enrollment_id' => $from->id],
                [
                    'student_id' => $request->student_id,
                    'academic_year_id' => $from->academic_year_id,
                    'from_grade_level_id' => $from->grade_level_id,
                    'from_branch_id' => $from->branch_id,
                    'to_enrollment_id' => $toEnrollment->id,
                    'to_grade_level_id' => $toEnrollment->grade_level_id,
                    'to_branch_id' => $toEnrollment->branch_id,
                    'decision' => PromotionDecision::Transferred,
                    'decided_by' => $approver->id,
                    'decided_at' => now(),
                    'executed_at' => now(),
                    'notes' => $request->reason,
                ],
            );

            $request->update([
                'status' => TransferRequestStatus::Approved,
                'to_enrollment_id' => $toEnrollment->id,
                'handover_snapshot' => $snapshot,
                'decided_by' => $approver->id,
                'decided_at' => now(),
            ]);

            ActivityLogger::log(
                actor: $approver,
                action: 'transfer.approved',
                subject: $request,
                properties: ['student_id' => $request->student_id],
                schoolId: $request->from_school_id,
                branchId: $request->from_branch_id,
            );

            return $request;
        });
    }
}
