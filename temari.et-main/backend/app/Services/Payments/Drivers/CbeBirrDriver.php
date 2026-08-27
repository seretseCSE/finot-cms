<?php

namespace App\Services\Payments\Drivers;

use App\Models\GatewayTransaction;
use App\Services\Payments\Contracts\GatewayDriver;
use App\Services\Payments\GatewayUnavailableException;
use App\Services\Payments\GatewayVerdict;
use Illuminate\Http\Request;

/**
 * CBE Birr direct. CBE exposes no public self-serve API — direct integration
 * needs a merchant agreement with the bank (until then CBE Birr is reachable
 * through Chapa's checkout). This driver is the reserved slot: it stays
 * disabled/unconfigured until the agreement lands, then the protocol goes
 * here and the rest of the platform needs no changes.
 */
class CbeBirrDriver implements GatewayDriver
{
    public function initiate(GatewayTransaction $transaction, string $returnUrl): string
    {
        throw new GatewayUnavailableException('CBE Birr direct integration is not yet live. Pay via Chapa to use CBE Birr.');
    }

    public function verify(GatewayTransaction $transaction): GatewayVerdict
    {
        return GatewayVerdict::pending();
    }

    public function webhookTxRef(Request $request): ?string
    {
        return null;
    }
}
