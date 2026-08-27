<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Models\FeeConcession;
use App\Models\Invoice;
use App\Models\StudentGuardian;

/**
 * Resolves which standing concession (if any) applies to a bill and stamps it
 * onto the invoice AT GENERATION TIME. The invoice's own discount fields stay
 * the frozen source of truth — revoking a concession later never rewrites
 * billed history. NO STACKING: when several concessions match, the single
 * largest cut wins.
 */
class FeeConcessionResolver
{
    /**
     * The best active concession for a billing context, judged by the actual
     * ETB cut on this gross amount. Guardian-level concessions reach the
     * student through their active guardian links.
     */
    public function bestFor(
        int $schoolId,
        int $branchId,
        int $studentId,
        float $gross,
        ?string $feeType,
        ?int $academicYearId,
        ?int $termId,
    ): ?FeeConcession {
        $candidates = FeeConcession::query()
            ->active()
            ->where('school_id', $schoolId)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where(function ($q) use ($studentId): void {
                $q->where('student_id', $studentId)
                    ->orWhereIn('parent_id', StudentGuardian::query()
                        ->where('student_id', $studentId)
                        ->where('is_active', true)
                        ->select('parent_id'));
            })
            // Fee-type scope: null = all fees. Ad-hoc invoices (no fee
            // structure) only match all-fee concessions.
            ->where(function ($q) use ($feeType): void {
                $q->whereNull('fee_types');
                if ($feeType !== null) {
                    $q->orWhereJsonContains('fee_types', $feeType);
                }
            })
            ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId))
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $termId))
            ->get();

        return $candidates
            ->filter(fn (FeeConcession $c): bool => $c->discountOn($gross) > 0)
            ->sortByDesc(fn (FeeConcession $c): float => $c->discountOn($gross))
            ->first();
    }

    /**
     * Stamp the winning concession onto a freshly generated invoice. No-op
     * when a discount is already on the invoice (manual grants win) or when
     * nothing applies. Recomputes the status so a full scholarship settles
     * the bill (and, via the gate, may activate a pending enrollment).
     */
    public function apply(Invoice $invoice, ?string $feeType): Invoice
    {
        if (($invoice->discount_type ?? DiscountType::None) !== DiscountType::None) {
            return $invoice;
        }

        $best = $this->bestFor(
            $invoice->school_id,
            $invoice->branch_id,
            $invoice->student_id,
            (float) $invoice->amount,
            $feeType,
            $invoice->academic_year_id,
            $invoice->term_id,
        );

        if ($best === null) {
            return $invoice;
        }

        return $this->stamp($invoice, $best);
    }

    /**
     * Retroactively stamp a concession onto the student's OPEN bills in its
     * scope — the create/approve flows offer this so a grant filed a moment
     * after billing (new sibling, fresh registration) still reaches the
     * invoice just created. Manual per-invoice grants and paid/void bills are
     * never touched; each bill still resolves through bestFor() so a larger
     * standing concession keeps winning. Returns how many invoices changed.
     */
    public function applyToOpenInvoices(FeeConcession $concession): int
    {
        $studentIds = $concession->student_id !== null
            ? [$concession->student_id]
            : StudentGuardian::query()
                ->where('parent_id', $concession->parent_id)
                ->where('is_active', true)
                ->pluck('student_id')
                ->all();

        if ($studentIds === []) {
            return 0;
        }

        $invoices = Invoice::query()
            ->where('school_id', $concession->school_id)
            ->when($concession->branch_id, fn ($q) => $q->where('branch_id', $concession->branch_id))
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->where('discount_type', DiscountType::None->value)
            ->when($concession->academic_year_id, fn ($q) => $q->where('academic_year_id', $concession->academic_year_id))
            ->when($concession->term_id, fn ($q) => $q->where('term_id', $concession->term_id))
            // Fee-type scope mirrors bestFor(): a typed concession only reaches
            // structure-backed bills of those types; all-fee grants reach every
            // bill including ad-hoc (structureless) ones.
            ->when($concession->fee_types !== null, fn ($q) => $q->whereHas(
                'feeStructure',
                fn ($s) => $s->whereIn('type', $concession->fee_types),
            ))
            ->with('feeStructure:id,type')
            ->get();

        $applied = 0;

        foreach ($invoices as $invoice) {
            $before = $invoice->fee_concession_id;
            $this->apply($invoice, $invoice->feeStructure?->type?->value);

            if ($invoice->fee_concession_id !== $before) {
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * Write the winning concession onto the invoice and recompute its status
     * against any payments already recorded. Skips (never throws) when
     * recorded money would exceed the new net — retro-application must not
     * orphan payments.
     */
    private function stamp(Invoice $invoice, FeeConcession $best): Invoice
    {
        $original = [
            'discount_type' => $invoice->discount_type,
            'discount_value' => $invoice->discount_value,
            'scholarship_reason' => $invoice->scholarship_reason,
        ];

        $invoice->discount_type = $best->discount_type;
        $invoice->discount_value = $best->discount_type === DiscountType::FullScholarship
            ? 0
            : $best->discount_value;
        $invoice->scholarship_reason = $best->reason ?: $best->category->label();
        $invoice->fee_concession_id = $best->id;

        $net = $invoice->netAmount();
        $paid = (float) $invoice->amount_paid;

        if ($paid > $net) {
            $invoice->forceFill([...$original, 'fee_concession_id' => null]);

            return $invoice;
        }

        $invoice->status = match (true) {
            $best->discount_type === DiscountType::FullScholarship => InvoiceStatus::Scholarship,
            $net <= 0 => InvoiceStatus::Scholarship,
            $paid >= $net && $paid > 0 => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };
        $invoice->save();

        // A concession that fully covers a registration fee lifts the gate.
        app(EnrollmentGate::class)->onInvoiceSettled($invoice);

        return $invoice;
    }
}
