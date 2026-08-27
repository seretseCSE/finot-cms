<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The registration-fee gate (school policy, `soft` by default). An enrollment
 * is born `pending` when an applicable registration fee is unsettled and
 * activates automatically the moment every registration invoice of its year
 * is paid or granted a scholarship. Soft gate: staff may provisionally activate early (the
 * invoice stays open and keeps its reminder machinery). Hard gate: payment or
 * a scholarship is the only door.
 */
class EnrollmentGate
{
    /**
     * Registration fee structures that apply to a (branch, year, grade) —
     * empty grade pivot = all grades.
     *
     * @return Collection<int, FeeStructure>
     */
    public function applicableRegistrationFees(int $branchId, int $academicYearId, int $gradeLevelId): Collection
    {
        return FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYearId)
            ->where('type', FeeType::Registration->value)
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereDoesntHave('gradeLevels')
                ->orWhereHas('gradeLevels', fn ($g) => $g->where('grade_levels.id', $gradeLevelId)))
            ->get();
    }

    /**
     * The status a new enrollment starts in: `pending` when any applicable
     * registration invoice is still open, `active` otherwise (no registration
     * fee configured, or already settled — e.g. paid inline in the wizard).
     */
    public function initialStatus(StudentEnrollment $enrollment): EnrollmentStatus
    {
        return $this->hasOpenRegistrationInvoice($enrollment)
            ? EnrollmentStatus::Pending
            : EnrollmentStatus::Active;
    }

    /**
     * Invoice settled (paid in full or granted a scholarship) — activate any pending
     * enrollment this registration fee was gating. Called by the payment,
     * discount and verification actions inside their transactions.
     */
    public function onInvoiceSettled(Invoice $invoice): void
    {
        if (! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Scholarship], true)) {
            return;
        }

        if ($invoice->feeStructure?->type !== FeeType::Registration) {
            return;
        }

        $pending = StudentEnrollment::query()
            ->where('student_id', $invoice->student_id)
            ->where('branch_id', $invoice->branch_id)
            ->where('academic_year_id', $invoice->academic_year_id)
            ->where('status', EnrollmentStatus::Pending->value)
            ->get();

        foreach ($pending as $enrollment) {
            if (! $this->hasOpenRegistrationInvoice($enrollment)) {
                $this->activate($enrollment, null, 'registration fee settled');
            }
        }
    }

    /**
     * Staff-initiated (provisional) activation. Always allowed once every
     * registration invoice is settled; before that only under the `soft`
     * school gate.
     */
    public function activateManually(StudentEnrollment $enrollment, ?User $actor): StudentEnrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending enrollments can be activated.'],
            ]);
        }

        $open = $this->hasOpenRegistrationInvoice($enrollment);

        if ($open && Branch::find($enrollment->branch_id)?->effectiveRegistrationGate() === 'hard') {
            throw ValidationException::withMessages([
                'status' => ['This school requires the registration fee to be settled before activation.'],
            ]);
        }

        return $this->activate($enrollment, $actor, $open ? 'provisional (fee unpaid)' : 'fee settled');
    }

    private function activate(StudentEnrollment $enrollment, ?User $actor, string $why): StudentEnrollment
    {
        $enrollment->update(['status' => EnrollmentStatus::Active]);

        ActivityLogger::log(
            actor: $actor,
            action: 'enrollment.activated',
            subject: $enrollment,
            properties: ['reason' => $why],
            schoolId: $enrollment->school_id,
            branchId: $enrollment->branch_id,
        );

        app(Notifier::class)->toFamily($enrollment->student, 'academics.enrollment_activated', [
            'school' => $enrollment->branch?->school?->name ?? '',
        ], [
            'link' => '/me/children',
            'schoolId' => $enrollment->school_id,
            'branchId' => $enrollment->branch_id,
        ]);

        return $enrollment;
    }

    private function hasOpenRegistrationInvoice(StudentEnrollment $enrollment): bool
    {
        return Invoice::query()
            ->where('student_id', $enrollment->student_id)
            ->where('branch_id', $enrollment->branch_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->whereHas('feeStructure', fn ($q) => $q->where('type', FeeType::Registration->value))
            ->exists();
    }
}
