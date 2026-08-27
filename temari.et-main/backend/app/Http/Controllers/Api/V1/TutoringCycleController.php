<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CycleStatus;
use App\Enums\GatewayPurpose;
use App\Http\Controllers\Controller;
use App\Models\TutoringCycle;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Tutoring\CycleReleaser;
use App\Support\ActivityLogger;
use App\Support\PaymentGateways;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The escrow months. Family lane: what's due + the gateway checkout
 * (payer-only). Platform lane (`marketplace.manage`): the money console —
 * every cycle across the marketplace, the release queue, releases and
 * refunds. The tutor sees cycles through their engagement detail.
 */
class TutoringCycleController extends Controller
{
    /** Family: my payable/paid months across engagements. */
    public function mine(Request $request): JsonResponse
    {
        $rows = TutoringCycle::query()
            ->whereHas('engagement', fn ($q) => $q->where('payer_user_id', $request->user()->id))
            ->with([
                'engagement:id,tutor_profile_id,student_id,payer_user_id,subjects',
                'engagement.tutorProfile:id,slug,user_id',
                'engagement.tutorProfile.user:id,name,avatar_path',
                'engagement.student:id,first_name,father_name',
            ])
            ->orderByRaw("CASE WHEN status = 'awaiting_payment' THEN 0 ELSE 1 END")
            ->orderByDesc('starts_on')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'cycles' => $rows->map(fn (TutoringCycle $c) => [
                'id' => $c->id,
                'engagement_id' => $c->engagement_id,
                'label' => $c->label,
                'status' => $c->status->value,
                'amount_due' => $c->amount_due,
                'gross_amount' => $c->gross_amount,
                'credit_applied' => $c->credit_applied,
                'planned_hours' => $c->planned_hours,
                'starts_on' => $c->starts_on->toDateString(),
                'ends_on' => $c->ends_on->toDateString(),
                'funded_at' => $c->funded_at?->toISOString(),
                'tutor_name' => $c->engagement?->tutorProfile?->user?->name,
                'tutor_avatar_url' => $c->engagement?->tutorProfile?->user?->avatarUrl(),
                'learner_name' => $c->engagement?->learnerName(),
            ])->values(),
            'gateways' => collect(PaymentGateways::availableFor(GatewayPurpose::TutoringCycle))
                ->map(fn (string $code) => ['code' => $code, 'label' => PaymentGateways::label($code)])
                ->values(),
        ]]);
    }

    /** Family: start the gateway checkout for one awaiting month. */
    public function pay(Request $request, TutoringCycle $cycle, PaymentGatewayManager $manager): JsonResponse
    {
        abort_unless($cycle->engagement?->payer_user_id === $request->user()->id, 404);
        abort_unless($cycle->status === CycleStatus::AwaitingPayment, 422, 'This month is already settled.');

        $data = $request->validate([
            'gateway' => ['required', Rule::in(PaymentGateways::CODES)],
        ]);

        $transaction = $manager->checkout(
            GatewayPurpose::TutoringCycle,
            $cycle,
            $request->user(),
            (string) $cycle->amount_due,
            $data['gateway'],
            rtrim((string) config('sms.frontend_url'), '/').'/pay/return?tx_ref={tx_ref}',
        );

        return response()->json(['data' => [
            'tx_ref' => $transaction->tx_ref,
            'checkout_url' => $transaction->checkout_url,
        ]]);
    }

    /** Platform money console: all cycles + rollup numbers. */
    public function console(Request $request, ?string $status = null): JsonResponse
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);

        $query = TutoringCycle::query()
            ->with([
                'engagement:id,tutor_profile_id,payer_user_id,student_id,commission_percent',
                'engagement.tutorProfile:id,slug,user_id',
                'engagement.tutorProfile.user:id,name',
                'engagement.payer:id,name,phone',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            // The release queue: funded + month over + nothing undecided.
            ->when($request->boolean('releasable'), fn ($q) => $q
                ->where('status', CycleStatus::Funded->value)
                ->where('ends_on', '<', now('Africa/Addis_Ababa')->toDateString())
                ->whereDoesntHave('sessions', fn ($s) => $s->whereIn('status', ['logged', 'disputed'])))
            ->orderByDesc('starts_on');

        $page = $query->paginate(min((int) $request->input('per_page', 25), 100));

        $page->getCollection()->transform(fn (TutoringCycle $c) => [
            'id' => $c->id,
            'engagement_id' => $c->engagement_id,
            'label' => $c->label,
            'status' => $c->status->value,
            'tutor_name' => $c->engagement?->tutorProfile?->user?->name,
            'payer_name' => $c->engagement?->payer?->name,
            'gross_amount' => $c->gross_amount,
            'amount_due' => $c->amount_due,
            'credit_applied' => $c->credit_applied,
            'commission_percent' => $c->commission_percent,
            'confirmed_hours' => $c->confirmed_hours,
            'commission_amount' => $c->commission_amount,
            'released_amount' => $c->released_amount,
            'credit_carried' => $c->credit_carried,
            'starts_on' => $c->starts_on->toDateString(),
            'ends_on' => $c->ends_on->toDateString(),
            'funded_at' => $c->funded_at?->toISOString(),
            'released_at' => $c->released_at?->toISOString(),
            'sessions_logged' => $c->sessions()->whereIn('status', ['logged'])->count(),
            'sessions_disputed' => $c->sessions()->where('status', 'disputed')->count(),
        ]);

        return response()->json($page);
    }

    /** Platform rollup for the console header. */
    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);

        $today = now('Africa/Addis_Ababa')->toDateString();

        return response()->json(['data' => [
            'awaiting_payment' => (float) TutoringCycle::query()->where('status', CycleStatus::AwaitingPayment->value)->sum('amount_due'),
            'held_in_escrow' => (float) TutoringCycle::query()->where('status', CycleStatus::Funded->value)->sum('amount_due'),
            'releasable_count' => TutoringCycle::query()
                ->where('status', CycleStatus::Funded->value)
                ->where('ends_on', '<', $today)
                ->whereDoesntHave('sessions', fn ($s) => $s->whereIn('status', ['logged', 'disputed']))
                ->count(),
            'commission_collected' => (float) TutoringCycle::query()->where('status', CycleStatus::Released->value)->sum('commission_amount'),
            'released_total' => (float) TutoringCycle::query()->where('status', CycleStatus::Released->value)->sum('released_amount'),
        ]]);
    }

    /** Platform: release one cycle's escrow to the tutor wallet. */
    public function release(Request $request, TutoringCycle $cycle, CycleReleaser $releaser): JsonResponse
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);

        $cycle = $releaser->release($cycle, $request->user());

        return response()->json([
            'data' => [
                'status' => $cycle->status->value,
                'released_amount' => $cycle->released_amount,
                'commission_amount' => $cycle->commission_amount,
                'credit_carried' => $cycle->credit_carried,
            ],
            'message' => __('Cycle released to the tutor wallet.'),
        ]);
    }

    /**
     * Platform: refund a funded cycle (engagement ended early, family
     * walks away). Records the decision; the actual money return happens
     * on the gateway/bank side and is noted here.
     */
    public function refund(Request $request, TutoringCycle $cycle): JsonResponse
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);
        abort_unless($cycle->status === CycleStatus::Funded, 422, 'Only funded, unreleased cycles can be refunded.');

        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $cycle->update([
            'status' => CycleStatus::Refunded->value,
            'refunded_at' => now(),
            'refunded_by' => $request->user()->id,
            'refund_note' => $data['note'],
        ]);

        ActivityLogger::log($request->user(), 'tutoring_cycle.refunded', $cycle, $data);

        return response()->json(['message' => __('Cycle marked refunded.')]);
    }
}
