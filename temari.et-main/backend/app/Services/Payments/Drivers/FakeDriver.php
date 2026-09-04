<?php

namespace App\Services\Payments\Drivers;

use App\Models\GatewayTransaction;
use App\Services\Payments\Contracts\GatewayDriver;
use App\Services\Payments\GatewayVerdict;
use Illuminate\Http\Request;

/**
 * The simulator: local/staging demos and tests. Checkout "redirects" to the
 * frontend simulator page, which calls POST payments/simulate/{tx} to decide
 * the outcome. PaymentGateways::configured() hard-blocks this driver in
 * production regardless of settings.
 */
class FakeDriver implements GatewayDriver
{
    public function initiate(GatewayTransaction $transaction, string $returnUrl): string
    {
        return rtrim((string) config('sms.frontend_url'), '/')
            .'/pay/simulate?tx_ref='.$transaction->tx_ref
            .'&return='.urlencode($returnUrl);
    }

    public function verify(GatewayTransaction $transaction): GatewayVerdict
    {
        return match ($transaction->raw['simulated'] ?? null) {
            'paid' => GatewayVerdict::paid('SIM-'.$transaction->tx_ref, ['simulated' => 'paid']),
            'failed' => GatewayVerdict::failed('Simulated failure', ['simulated' => 'failed']),
            default => GatewayVerdict::pending(),
        };
    }

    public function webhookTxRef(Request $request): ?string
    {
        return null;
    }
}
