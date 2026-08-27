<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GatewayTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The payer's view of their own gateway transactions: the return-page poll
 * (which triggers a server-side verify) and the local/staging simulator.
 * Strictly self-scoped — a transaction belongs to the user who started it.
 */
class GatewayTransactionController extends Controller
{
    /** Return-page poll: verify with the gateway, answer current status. */
    public function show(Request $request, string $txRef, PaymentGatewayManager $manager): JsonResponse
    {
        $transaction = GatewayTransaction::query()
            ->where('tx_ref', $txRef)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $transaction = $manager->verifyNow($transaction);

        return response()->json(['data' => [
            'tx_ref' => $transaction->tx_ref,
            'gateway' => $transaction->gateway,
            'gateway_label' => $transaction->gatewayLabel(),
            'purpose' => $transaction->purpose->value,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status->value,
            'failure_reason' => $transaction->failure_reason,
            'paid_at' => $transaction->paid_at?->toISOString(),
            'checkout_url' => $transaction->status === GatewayTransactionStatus::Pending ? $transaction->checkout_url : null,
        ]]);
    }

    /**
     * Simulator outcome (fake gateway only, never in production): the payer
     * decides paid/failed on the simulate page, then the manager settles
     * through the exact same path a real gateway would.
     */
    public function simulate(Request $request, string $txRef, PaymentGatewayManager $manager): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['paid', 'failed'])],
        ]);

        $transaction = GatewayTransaction::query()
            ->where('tx_ref', $txRef)
            ->where('gateway', 'fake')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_if(app()->isProduction(), 404);

        $transaction->update(['raw' => ['simulated' => $data['outcome']]]);
        $transaction = $manager->verifyNow($transaction);

        return response()->json(['data' => ['status' => $transaction->status->value]]);
    }
}
