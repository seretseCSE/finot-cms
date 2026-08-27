<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GatewayPurpose;
use App\Http\Controllers\Controller;
use App\Models\AiSubscription;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\PaymentGateways;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The B2C parent/student AI upgrade (CLAUDE.md §11): 199 ETB / 30 days,
 * collected through the payment gateway — Temari's OWN subscription money,
 * never school fees. Fulfilment (activation/extension) happens in
 * AiSubscription::gatewayPaid inside the gateway manager's settle.
 */
class AiSubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => [
            'plan' => (string) config('temari-ai.subscription.plan'),
            'price_etb' => (float) config('temari-ai.subscription.price_etb'),
            'days' => (int) config('temari-ai.subscription.days'),
            'gateways' => PaymentGateways::availableFor(GatewayPurpose::AiSubscription),
        ]]);
    }

    public function mine(Request $request): JsonResponse
    {
        $active = AiSubscription::query()
            ->activeFor($request->user()->id)
            ->orderByDesc('ends_at')
            ->first();

        return response()->json(['data' => [
            'active' => $active !== null,
            'ends_at' => $active?->ends_at?->toISOString(),
        ]]);
    }

    public function subscribe(Request $request, PaymentGatewayManager $manager): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', Rule::in(PaymentGateways::CODES)],
        ]);

        $price = (float) config('temari-ai.subscription.price_etb');

        $subscription = AiSubscription::create([
            'user_id' => $request->user()->id,
            'plan' => (string) config('temari-ai.subscription.plan'),
            'amount' => $price,
            'status' => 'pending_payment',
        ]);

        $transaction = $manager->checkout(
            GatewayPurpose::AiSubscription,
            $subscription,
            $request->user(),
            (string) $price,
            $data['gateway'],
            rtrim((string) config('sms.frontend_url'), '/').'/pay/return?tx_ref={tx_ref}',
        );

        return response()->json(['data' => [
            'tx_ref' => $transaction->tx_ref,
            'checkout_url' => $transaction->checkout_url,
        ]], 201);
    }
}
