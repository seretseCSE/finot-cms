<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Ethiopia;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only receivables analytics over invoices + payments. Every method
 * takes base queries the controller has ALREADY tenant-scoped (branch /
 * school / filters) — the service only aggregates within them. All heavy
 * lifting is grouped SQL on the (branch_id, status) / (branch_id, paid_at) /
 * (branch_id, due_date) indexes; nothing loads a row per student.
 */
class FeeReportService
{
    /**
     * Headline receivables KPIs + the aging ladder + payment-method mix.
     *
     * @param  Builder<Invoice>  $invoices
     * @param  Builder<Payment>  $payments
     * @return array<string, mixed>
     */
    public function overview(Builder $invoices, Builder $payments): array
    {
        $due = Invoice::totalDueSql();
        $today = Ethiopia::today();
        $open = "status IN ('unpaid', 'partial')";

        $row = (clone $invoices)->toBase()->selectRaw(
            <<<SQL
            COUNT(*) FILTER (WHERE status != 'void') AS invoices,
            COALESCE(SUM(CASE WHEN status != 'void' THEN {$due} ELSE 0 END), 0) AS invoiced,
            COALESCE(SUM(CASE WHEN status != 'void' THEN amount_paid ELSE 0 END), 0) AS collected,
            COALESCE(SUM(CASE WHEN {$open} THEN ({$due}) - amount_paid ELSE 0 END), 0) AS outstanding,
            COUNT(*) FILTER (WHERE {$open} AND due_date < '{$today}') AS overdue_count,
            COALESCE(SUM(CASE WHEN {$open} AND due_date < '{$today}' THEN ({$due}) - amount_paid ELSE 0 END), 0) AS overdue_amount,
            COALESCE(SUM(CASE WHEN status != 'void' THEN penalty_amount ELSE 0 END), 0) AS penalties_accrued,
            COUNT(DISTINCT student_id) FILTER (WHERE {$open}) AS students_owing
            SQL,
        )->first();

        $invoiced = (float) ($row->invoiced ?? 0);
        $collected = (float) ($row->collected ?? 0);

        return [
            'invoices' => (int) ($row->invoices ?? 0),
            'invoiced' => self::money($invoiced),
            'collected' => self::money($collected),
            'outstanding' => self::money((float) ($row->outstanding ?? 0)),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'overdue_amount' => self::money((float) ($row->overdue_amount ?? 0)),
            'penalties_accrued' => self::money((float) ($row->penalties_accrued ?? 0)),
            'students_owing' => (int) ($row->students_owing ?? 0),
            'collection_rate' => $invoiced > 0 ? round($collected / $invoiced * 100, 1) : null,
            'aging' => $this->aging(clone $invoices),
            'methods' => $this->methodMix(clone $payments),
        ];
    }

    /**
     * Outstanding balance bucketed by days past due — the receivables aging
     * ladder (current / 1–30 / 31–60 / 61–90 / 90+).
     *
     * @param  Builder<Invoice>  $invoices
     * @return list<array{bucket: string, amount: string, count: int}>
     */
    public function aging(Builder $invoices): array
    {
        $due = Invoice::totalDueSql();
        $today = Ethiopia::today();

        $bucket = <<<SQL
            CASE
                WHEN due_date IS NULL OR due_date >= '{$today}' THEN 'current'
                WHEN due_date >= DATE '{$today}' - 30 THEN '1-30'
                WHEN due_date >= DATE '{$today}' - 60 THEN '31-60'
                WHEN due_date >= DATE '{$today}' - 90 THEN '61-90'
                ELSE '90+'
            END
            SQL;

        $rows = $invoices->toBase()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->selectRaw("{$bucket} AS bucket, COALESCE(SUM(({$due}) - amount_paid), 0) AS amount, COUNT(*) AS count")
            ->groupByRaw($bucket)
            ->get()
            ->keyBy('bucket');

        return collect(['current', '1-30', '31-60', '61-90', '90+'])
            ->map(fn (string $key): array => [
                'bucket' => $key,
                'amount' => self::money((float) ($rows[$key]->amount ?? 0)),
                'count' => (int) ($rows[$key]->count ?? 0),
            ])
            ->all();
    }

    /**
     * Collections per day with a per-method split — the cashier's daily
     * collection report.
     *
     * @param  Builder<Payment>  $payments
     * @return array{days: list<array<string, mixed>>, methods: list<array<string, mixed>>, cashiers: list<array<string, mixed>>, total: string, count: int}
     */
    public function dailyCollections(Builder $payments): array
    {
        $days = (clone $payments)->toBase()
            ->selectRaw('paid_at, method, COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS count')
            ->groupBy('paid_at', 'method')
            ->orderBy('paid_at')
            ->get()
            ->groupBy('paid_at')
            ->map(fn (Collection $rows, string $date): array => [
                'date' => $date,
                'total' => self::money((float) $rows->sum('amount')),
                'count' => (int) $rows->sum('count'),
                'methods' => $rows->map(fn ($r): array => [
                    'method' => $r->method,
                    'amount' => self::money((float) $r->amount),
                    'count' => (int) $r->count,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $cashiers = (clone $payments)->toBase()
            ->leftJoin('users', 'users.id', '=', 'payments.recorded_by')
            ->selectRaw('payments.recorded_by, users.name, COALESCE(SUM(payments.amount), 0) AS amount, COUNT(*) AS count')
            ->groupBy('payments.recorded_by', 'users.name')
            ->orderByRaw('SUM(payments.amount) DESC')
            ->get()
            ->map(fn ($r): array => [
                'user_id' => $r->recorded_by,
                'name' => $r->name ?? 'Family submissions',
                'amount' => self::money((float) $r->amount),
                'count' => (int) $r->count,
            ])
            ->all();

        $total = (clone $payments)->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS count')
            ->first();

        return [
            'days' => $days,
            'methods' => $this->methodMix(clone $payments),
            'cashiers' => $cashiers,
            'total' => self::money((float) ($total->amount ?? 0)),
            'count' => (int) ($total->count ?? 0),
        ];
    }

    /**
     * @param  Builder<Payment>  $payments
     * @return list<array{method: string, amount: string, count: int}>
     */
    private function methodMix(Builder $payments): array
    {
        return $payments->toBase()
            ->selectRaw('method, COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS count')
            ->groupBy('method')
            ->orderByRaw('SUM(amount) DESC')
            ->get()
            ->map(fn ($r): array => [
                'method' => $r->method,
                'amount' => self::money((float) $r->amount),
                'count' => (int) $r->count,
            ])
            ->all();
    }

    private static function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
