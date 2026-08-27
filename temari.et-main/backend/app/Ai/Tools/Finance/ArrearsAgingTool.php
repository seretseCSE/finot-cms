<?php

namespace App\Ai\Tools\Finance;

use App\Ai\Tools\Leadership\LeadershipScopedTool;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Receivables aging + the follow-up list: overdue balance bucketed by age
 * and the families owing the most — who to call first.
 */
class ArrearsAgingTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'Receivables aging: overdue balances bucketed (0–30/31–60/61–90/90+ days) and the top outstanding students with amounts (ETB). Use for arrears, defaulters, or "who should finance follow up with".';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('fees.reports.view', 'fees.view')) !== null) {
            return $this->deny($denied);
        }

        $branchIds = $this->branchIds($request->integer('branch_id') ?: null);

        $overdue = Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());

        $buckets = (clone $overdue)
            ->selectRaw("case
                    when current_date - due_date <= 30 then '0-30'
                    when current_date - due_date <= 60 then '31-60'
                    when current_date - due_date <= 90 then '61-90'
                    else '90+' end as bucket,
                count(*) as invoices,
                sum(amount - amount_paid) as balance")
            ->groupBy('bucket')
            ->get()
            ->map(fn ($row): array => [
                'bucket_days' => $row->bucket,
                'invoices' => (int) $row->invoices,
                'balance_etb' => (float) $row->balance,
            ]);

        $top = (clone $overdue)
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->selectRaw("concat_ws(' ', students.first_name, students.father_name) as student,
                students.id as student_id,
                count(*) as invoices,
                sum(invoices.amount - invoices.amount_paid) as balance")
            ->groupBy('students.id', 'students.first_name', 'students.father_name')
            ->orderByDesc('balance')
            ->limit(15)
            ->get()
            ->map(fn ($row): array => [
                'student' => $row->student,
                'overdue_invoices' => (int) $row->invoices,
                'balance_etb' => (float) $row->balance,
            ]);

        return $this->ok([
            'aging_buckets' => $buckets,
            'total_overdue_etb' => round((float) $buckets->sum('balance_etb'), 2),
            'top_outstanding' => $top,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
