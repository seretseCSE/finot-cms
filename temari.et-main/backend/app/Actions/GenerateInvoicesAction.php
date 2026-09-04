<?php

namespace App\Actions;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\StudentEnrollment;
use App\Services\FeeConcessionResolver;
use App\Services\Notify\Notifier;
use App\Support\BillingPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Issues an invoice from a fee structure to every actively-enrolled student it
 * applies to (its applicable grades, or all grades when none are pinned) for the
 * structure's academic year. Idempotent per (fee_structure, term, student) —
 * or per (fee_structure, billing period, student) when a period is given, the
 * recurring-billing lane. Returns the number of invoices created.
 */
class GenerateInvoicesAction
{
    public function __construct(
        private readonly FeeConcessionResolver $concessions,
        private readonly Notifier $notifier,
    ) {
    }

    public function execute(FeeStructure $feeStructure, ?int $termId = null, ?BillingPeriod $period = null): int
    {
        return DB::transaction(function () use ($feeStructure, $termId, $period): int {
            $gradeIds = $feeStructure->gradeLevels()->pluck('grade_levels.id');

            $enrollments = StudentEnrollment::query()
                ->where('branch_id', $feeStructure->branch_id)
                ->where('academic_year_id', $feeStructure->academic_year_id)
                ->where('status', EnrollmentStatus::Active->value)
                ->when(
                    $gradeIds->isNotEmpty(),
                    fn ($q) => $q->whereIn('grade_level_id', $gradeIds),
                )
                ->with('student')
                ->get();

            $created = 0;

            foreach ($enrollments as $enrollment) {
                $invoice = $this->issue($feeStructure, $enrollment, $termId, $period);

                if ($invoice?->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        });
    }

    /**
     * Issue this structure's invoice to ONE enrollment (the registration-wizard
     * path). Same idempotency contract as the bulk fan-out.
     */
    public function executeForEnrollment(FeeStructure $feeStructure, StudentEnrollment $enrollment, ?int $termId = null): Invoice
    {
        return DB::transaction(
            fn (): Invoice => $this->issue($feeStructure, $enrollment, $termId, null),
        );
    }

    /**
     * Null only when a billing period predates the enrollment — a student who
     * joined in Tir is never billed Meskerem.
     */
    private function issue(FeeStructure $feeStructure, StudentEnrollment $enrollment, ?int $termId, ?BillingPeriod $period): ?Invoice
    {
        if ($period !== null && $enrollment->enrolled_on !== null
            && $enrollment->enrolled_on->toImmutable()->greaterThan($period->end)) {
            return null;
        }

        $invoice = $feeStructure->invoices()->firstOrCreate(
            [
                'student_id' => $enrollment->student_id,
                'term_id' => $termId,
                'billing_year' => $period?->year,
                'billing_month' => $period?->month,
            ],
            [
                'school_id' => $feeStructure->school_id,
                'branch_id' => $feeStructure->branch_id,
                'academic_year_id' => $feeStructure->academic_year_id,
                'title' => $period === null
                    ? $feeStructure->name
                    : $feeStructure->name.' — '.$period->label,
                'amount' => $this->amountFor($feeStructure, $enrollment, $period),
                'amount_paid' => 0,
                'due_date' => $period?->due ?? $feeStructure->due_on,
                'status' => InvoiceStatus::Unpaid->value,
            ],
        );

        // Standing concessions stamp fresh invoices only — an existing bill's
        // discount history is never rewritten by a later policy change.
        if ($invoice->wasRecentlyCreated) {
            $invoice = $this->concessions->apply($invoice, $feeStructure->type->value);

            // Fully-covered bills (scholarship) announce nothing to pay.
            if ($invoice->status !== InvoiceStatus::Scholarship) {
                $this->notifier->toFamily($enrollment->loadMissing('student')->student, 'finance.invoice_issued', [
                    'fee' => $invoice->title,
                    'amount' => (string) $invoice->balance,
                ], [
                    'link' => '/me/payments',
                    'schoolId' => $invoice->school_id,
                    'branchId' => $invoice->branch_id,
                    'dedupeKey' => "invoice_issued:{$invoice->id}",
                ]);
            }
        }

        return $invoice;
    }

    /**
     * The period charge for THIS student: the full fee, or a daily proration
     * when the branch prorates and the student joined mid-period.
     */
    private function amountFor(FeeStructure $feeStructure, StudentEnrollment $enrollment, ?BillingPeriod $period): string
    {
        $amount = (float) $feeStructure->amount;

        if ($period === null || $enrollment->enrolled_on === null) {
            return number_format($amount, 2, '.', '');
        }

        $enrolled = $enrollment->enrolled_on->toImmutable()->startOfDay();

        if ($enrolled->lessThanOrEqualTo($period->start)
            || $feeStructure->branch?->effectiveFeeProration() !== 'daily') {
            return number_format($amount, 2, '.', '');
        }

        $periodDays = (int) $period->start->diffInDays($period->end) + 1;
        $remaining = (int) $enrolled->diffInDays($period->end) + 1;

        return number_format(round($amount * $remaining / $periodDays, 2), 2, '.', '');
    }
}
