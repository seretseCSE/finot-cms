<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PromotionDecision;
use App\Models\Invoice;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentWithdrawal;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mid-year withdrawal: the student leaves the school entirely or moves to a
 * school OUTSIDE Temari (in-platform moves go through the transfer flow).
 * Closes the live enrollment (`withdrawn`, freeing its seat), snapshots the
 * outstanding balance onto the student_withdrawals row that backs the
 * clearance letter, and writes the student_promotions audit row so academic
 * history stays walkable. Outstanding fees never BLOCK a withdrawal — they
 * are noted on the letter instead; the invoices stay open as a debt record.
 */
class WithdrawStudentAction
{
    /**
     * @param  array{reason: string, destination?: ?string, withdrawn_on?: ?string}  $data
     */
    public function execute(StudentEnrollment $enrollment, array $data, User $actor): StudentWithdrawal
    {
        return DB::transaction(function () use ($enrollment, $data, $actor): StudentWithdrawal {
            /** @var StudentEnrollment $enrollment */
            $enrollment = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);

            if (! in_array($enrollment->status, [EnrollmentStatus::Pending, EnrollmentStatus::Active], true)) {
                throw ValidationException::withMessages([
                    'enrollment' => ['Only a live (pending or active) enrollment can be withdrawn.'],
                ]);
            }

            $withdrawnOn = $data['withdrawn_on'] ?? now()->toDateString();

            $enrollment->update([
                'status' => EnrollmentStatus::Withdrawn->value,
                'exited_on' => $withdrawnOn,
            ]);

            $withdrawal = StudentWithdrawal::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'school_id' => $enrollment->school_id,
                'branch_id' => $enrollment->branch_id,
                'reason' => $data['reason'],
                'destination' => $data['destination'] ?? null,
                'withdrawn_on' => $withdrawnOn,
                'outstanding_amount' => $this->outstandingFor($enrollment),
                'withdrawn_by' => $actor->id,
            ]);

            // Same audit table the transfer handover and year-end board write —
            // one place where a student's movements stay walkable.
            StudentPromotion::updateOrCreate(
                ['from_enrollment_id' => $enrollment->id],
                [
                    'student_id' => $enrollment->student_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'from_grade_level_id' => $enrollment->grade_level_id,
                    'from_branch_id' => $enrollment->branch_id,
                    'decision' => PromotionDecision::Withdrawn,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'executed_at' => now(),
                    'notes' => $data['reason'],
                ],
            );

            ActivityLogger::log(
                actor: $actor,
                action: 'enrollment.withdrawn',
                subject: $withdrawal,
                properties: [
                    'student_id' => $enrollment->student_id,
                    'outstanding_amount' => (string) $withdrawal->outstanding_amount,
                ],
                schoolId: $enrollment->school_id,
                branchId: $enrollment->branch_id,
            );

            return $withdrawal;
        });
    }

    /**
     * The student's open balance at THIS branch for the enrollment's year:
     * every unpaid/partial invoice's net + penalty minus what was already paid.
     */
    private function outstandingFor(StudentEnrollment $enrollment): float
    {
        $net = Invoice::totalDueSql();

        $sum = Invoice::query()
            ->where('student_id', $enrollment->student_id)
            ->where('branch_id', $enrollment->branch_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->selectRaw("COALESCE(SUM(({$net}) - amount_paid), 0) AS outstanding")
            ->value('outstanding');

        return round(max((float) $sum, 0), 2);
    }
}
