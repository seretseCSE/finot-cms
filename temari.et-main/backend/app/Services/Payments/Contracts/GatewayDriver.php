<?php

namespace App\Services\Payments\Contracts;

use App\Models\GatewayTransaction;
use App\Services\Payments\GatewayUnavailableException;
use App\Services\Payments\GatewayVerdict;
use Illuminate\Http\Request;

/**
 * One payment gateway's protocol. Drivers TALK to the gateway only — every
 * state transition goes through PaymentGatewayManager, and a webhook is
 * never trusted on its own: the manager re-verifies server-side before
 * settling (verify() is the authority, the webhook is just the doorbell).
 */
interface GatewayDriver
{
    /**
     * Create the hosted checkout for this transaction.
     *
     * @return string the checkout URL to send the payer to
     *
     * @throws GatewayUnavailableException
     */
    public function initiate(GatewayTransaction $transaction, string $returnUrl): string;

    /** Ask the gateway for the transaction's current truth. */
    public function verify(GatewayTransaction $transaction): GatewayVerdict;

    /**
     * Validate an incoming webhook and extract our tx_ref (null when the
     * event is not about a collection we track). MUST reject bad signatures.
     */
    public function webhookTxRef(Request $request): ?string;
}
