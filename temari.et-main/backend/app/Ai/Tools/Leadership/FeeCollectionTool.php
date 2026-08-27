<?php

namespace App\Ai\Tools\Leadership;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Fee collection health: billed vs collected, outstanding balance, and
 * collection by month. Kernel-gated on fees.reports.view — for a director
 * that permission only exists when the school flipped
 * director_finance_access (FinanceControls), so the gate is simply the
 * permission check, same as the finance report screens.
 */
class FeeCollectionTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Fee collection summary: total billed, collected, outstanding (ETB), unpaid/partial invoice counts, and recent monthly collection totals. Use for questions about fee collection or revenue from school fees.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('fees.reports.view', 'fees.view')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $invoices = Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
            ->selectRaw("count(*) as invoices,
                sum(amount) as billed,
                sum(amount_paid) as collected,
                count(*) filter (where status = 'unpaid') as unpaid_count,
                count(*) filter (where status = 'partial') as partial_count,
                count(*) filter (where status in ('unpaid','partial') and due_date < current_date) as overdue_count")
            ->first();

        $monthly = Payment::query()
            ->whereIn('branch_id', $branchIds)
            ->where('paid_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("to_char(paid_at, 'YYYY-MM') as month, sum(amount) as collected, count(*) as payments")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row): array => [
                'month' => $row->month,
                'collected_etb' => (float) $row->collected,
                'payments' => (int) $row->payments,
            ]);

        return $this->ok([
            'active_year' => [
                'invoices' => (int) $invoices->invoices,
                'billed_etb' => (float) $invoices->billed,
                'collected_etb' => (float) $invoices->collected,
                'outstanding_etb' => round((float) $invoices->billed - (float) $invoices->collected, 2),
                'collection_rate_percent' => (float) $invoices->billed > 0
                    ? round((float) $invoices->collected * 100 / (float) $invoices->billed, 1)
                    : null,
                'unpaid_invoices' => (int) $invoices->unpaid_count,
                'partial_invoices' => (int) $invoices->partial_count,
                'overdue_invoices' => (int) $invoices->overdue_count,
            ],
            'monthly_collections' => $monthly,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
