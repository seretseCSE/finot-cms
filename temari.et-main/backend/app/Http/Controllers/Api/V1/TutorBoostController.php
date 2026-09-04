<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GatewayPurpose;
use App\Enums\TutorStatus;
use App\Http\Controllers\Controller;
use App\Models\ProfileBoost;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Marketplace;
use App\Support\PaymentGateways;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Paid directory placement, tutor lane: plans (operator-priced), purchase
 * through the gateway (the ProfileBoost is the payable — payment activates
 * it and extends boosted_until), and my boost history.
 */
class TutorBoostController extends Controller
{
    public function plans(Request $request): JsonResponse
    {
        $settings = Marketplace::settings();
        $profile = $request->user()->tutorProfile()->first();

        return response()->json(['data' => [
            'plans' => [
                ['plan' => 'weekly', 'days' => 7, 'price' => $settings['boost_weekly_price']],
                ['plan' => 'monthly', 'days' => 30, 'price' => $settings['boost_monthly_price']],
            ],
            'boosted_until' => $profile?->boosted_until?->toISOString(),
            'gateways' => collect(PaymentGateways::availableFor(GatewayPurpose::ProfileBoost))
                ->map(fn (string $code) => ['code' => $code, 'label' => PaymentGateways::label($code)])
                ->values(),
        ]]);
    }

    public function store(Request $request, PaymentGatewayManager $manager): JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();

        abort_if($profile === null, 403);
        abort_unless($profile->status === TutorStatus::Approved, 422, 'Only approved tutors can boost their profile.');

        $data = $request->validate([
            'plan' => ['required', Rule::in(['weekly', 'monthly'])],
            'gateway' => ['required', Rule::in(PaymentGateways::CODES)],
        ]);

        $settings = Marketplace::settings();
        $price = $data['plan'] === 'monthly' ? $settings['boost_monthly_price'] : $settings['boost_weekly_price'];

        $boost = ProfileBoost::create([
            'tutor_profile_id' => $profile->id,
            'plan' => $data['plan'],
            'amount' => $price,
        ]);

        $transaction = $manager->checkout(
            GatewayPurpose::ProfileBoost,
            $boost,
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

    public function mine(Request $request): JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();
        abort_if($profile === null, 403);

        $rows = ProfileBoost::query()
            ->where('tutor_profile_id', $profile->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $rows->map(fn (ProfileBoost $b) => [
            'id' => $b->id,
            'plan' => $b->plan,
            'amount' => $b->amount,
            'status' => $b->status,
            'starts_at' => $b->starts_at?->toISOString(),
            'ends_at' => $b->ends_at?->toISOString(),
            'created_at' => $b->created_at?->toISOString(),
        ])->values()]);
    }
}
