<?php

namespace App\Ai\Tools\Family;

use App\Enums\AiLane;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Parent lane: the child's fee position — open invoices with balances and
 * due dates, plus recent payments. Guarded by the link's can_pay_fees flag;
 * refused entirely in the student lane (fees are guardian business).
 */
class StudentFeesTool extends StudentScopedTool
{
    public function description(): Stringable|string
    {
        return 'Get the child\'s school-fee position: unpaid/partial invoices (amount, paid so far, due date, penalty) and recent payments. Use for questions about fees owed, due dates, or payment history. Amounts are in ETB.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->context->lane === AiLane::Student) {
            return $this->deny('Fee details are available to guardians, not student accounts.');
        }

        [$student, $link, $denial] = $this->resolveStudent($request->integer('student_id') ?: null);

        if ($denial !== null) {
            return $this->deny($denial);
        }

        if (! $this->linkAllows($link, 'can_pay_fees')) {
            return $this->deny('Your guardian link does not permit viewing this student\'s fees.');
        }

        $open = Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->orderBy('due_date')
            ->limit(20)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'title' => $invoice->title ?? 'Invoice #'.$invoice->id,
                'amount_etb' => (float) $invoice->amount,
                'paid_etb' => (float) ($invoice->amount_paid ?? 0),
                'penalty_etb' => $invoice->penalty_waived ? 0.0 : (float) ($invoice->penalty_amount ?? 0),
                'due_date' => $invoice->due_date instanceof \DateTimeInterface
                    ? $invoice->due_date->format('Y-m-d')
                    : $invoice->due_date,
                'status' => $invoice->status,
            ]);

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment): array => [
                'amount_etb' => (float) $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at instanceof \DateTimeInterface
                    ? $payment->paid_at->format('Y-m-d')
                    : $payment->paid_at,
                'receipt_number' => $payment->receipt_number,
            ]);

        return $this->ok([
            'student' => $student->full_name,
            'open_invoices' => $open,
            'total_outstanding_etb' => round($open->sum(fn (array $i) => $i['amount_etb'] + $i['penalty_etb'] - $i['paid_etb']), 2),
            'recent_payments' => $payments,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('The child to look at (from my_children).'),
        ];
    }
}
