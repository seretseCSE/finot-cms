<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\PaymentGateways;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public webhook door for every gateway. Always answers 200 (gateways
 * retry on anything else and we never leak validation detail); a webhook is
 * only ever a doorbell — the manager re-verifies server-side before any
 * money state changes.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $manager): JsonResponse
    {
        if (in_array($gateway, PaymentGateways::CODES, true)) {
            rescue(fn () => $manager->handleWebhook($gateway, $request), report: true);
        }

        return response()->json(['status' => 'ok']);
    }
}
