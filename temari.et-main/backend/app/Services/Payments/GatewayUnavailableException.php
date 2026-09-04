<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * The gateway cannot take this payment right now (missing credentials,
 * transport failure, gateway-side rejection of the checkout). Surfaces to
 * the payer as a retryable 422 — never a 500.
 */
class GatewayUnavailableException extends RuntimeException
{
}
