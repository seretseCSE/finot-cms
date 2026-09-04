<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\GeneratedDocument;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Analytics\Analytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Invoice $invoice,
        RecordPaymentAction $action,
    ): JsonResponse {
        $this->authorize('recordPayment', $invoice);

        $payment = $action->execute($invoice, $request->validated(), $request->user()->id);

        Analytics::capture($request->user(), 'payment.recorded', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
        ], $payment->school_id, $payment->branch_id);

        return (new PaymentResource($payment->load('bankAccount.bank')))
            ->additional([
                'message' => 'Payment recorded.',
                'meta' => [
                    'invoice' => new InvoiceResource($invoice->refresh()->load([
                        'student:id,first_name,father_name,grandfather_name,public_id',
                        'payments.bankAccount.bank:id,code,name,type,logo',
                    ])),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Printable official-receipt payload for one payment. The QR on the
     * printout points at the public verification route below.
     */
    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('fees.view', $payment->school_id, $payment->branch_id),
            403,
        );

        return response()->json(['data' => self::receiptPayload($payment)]);
    }

    /**
     * UNAUTHENTICATED receipt view behind the unguessable token — what the
     * QR code on the printed receipt resolves to. Confirms the receipt is
     * genuine without exposing anything beyond what the paper already shows.
     */
    public function publicReceipt(string $token): JsonResponse
    {
        $payment = Payment::query()
            ->where('receipt_token', $token)
            ->firstOrFail();

        // Printing goes through the OFFICIAL PDF (pre-warmed when the payment
        // was recorded), never the browser's print of this web page.
        return response()->json(['data' => [
            ...self::receiptPayload($payment),
            ...GeneratedDocument::publicUrlsFor('payment_receipt', $payment),
        ]]);
    }

    /**
     * Also feeds the backend PDF receipt (PaymentReceiptDocument).
     *
     * @return array<string, mixed>
     */
    public static function receiptPayload(Payment $payment): array
    {
        $payment->load([
            'student:id,first_name,father_name,grandfather_name,public_id',
            'invoice:id,title,amount,amount_paid,discount_type,discount_value,penalty_amount,penalty_waived,status',
            'bankAccount.bank:id,code,name,type,logo',
            'recorder:id,name',
            'branch.school:id,name',
        ]);

        $invoice = $payment->invoice;

        return [
            'receipt_number' => $payment->receipt_number,
            'public_token' => $payment->receipt_token,
            'school' => $payment->branch?->school?->name,
            'branch' => $payment->branch?->name,
            'student' => [
                'full_name' => $payment->student?->full_name,
                'public_id' => $payment->student?->public_id,
            ],
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => sprintf('INV-%06d', $payment->invoice_id),
            'invoice_title' => $invoice?->title,
            'amount' => $payment->amount,
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'reference' => $payment->reference,
            'bank_account' => $payment->bankAccount === null ? null : [
                'account_name' => $payment->bankAccount->account_name,
                'account_number' => $payment->bankAccount->account_number,
                'bank_name' => $payment->bankAccount->bank?->name,
            ],
            'paid_at' => $payment->paid_at?->toDateString(),
            'recorded_by' => $payment->recorder?->name,
            'invoice_total_due' => $invoice === null ? null : number_format($invoice->totalDue(), 2, '.', ''),
            'invoice_amount_paid' => $invoice?->amount_paid,
            'invoice_status' => $invoice?->status?->value,
            'issued_at' => $payment->created_at?->toIso8601String(),
        ];
    }
}
