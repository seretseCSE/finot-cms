<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\TutorLedgerEntry;
use App\Models\TutorPayout;
use App\Services\Notify\Notifier;
use App\Services\Payments\Drivers\ChapaDriver;
use App\Services\Tutoring\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tutor withdrawals + the wallet history (tutor lane), and the Temari.et
 * payout desk (`marketplace.manage`): approve → pay via Chapa transfer or
 * record a manual bank transfer → or reverse, crediting the reservation
 * back. Every money movement is a TutorLedger post.
 */
class TutorPayoutController extends Controller
{
    /** Tutor: wallet history + my payouts. */
    public function mine(Request $request): JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();
        abort_if($profile === null, 403);

        return response()->json(['data' => [
            'wallet_balance' => (string) $profile->wallet_balance,
            'payout_account' => [
                'bank_code' => $profile->payout_bank_code,
                'bank_name' => $profile->payout_bank_name,
                'account_number' => $profile->payout_account_number,
                'account_name' => $profile->payout_account_name,
            ],
            'ledger' => TutorLedgerEntry::query()
                ->where('tutor_profile_id', $profile->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (TutorLedgerEntry $e) => [
                    'id' => $e->id,
                    'entry_type' => $e->entry_type,
                    'amount' => $e->amount,
                    'balance_after' => $e->balance_after,
                    'memo' => $e->memo,
                    'created_at' => $e->created_at?->toISOString(),
                ])->values(),
            'payouts' => TutorPayout::query()
                ->where('tutor_profile_id', $profile->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (TutorPayout $p) => $this->payload($p))->values(),
        ]]);
    }

    /** Tutor: request a withdrawal. */
    public function store(Request $request, PayoutService $service): JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();
        abort_if($profile === null, 403);

        $data = $request->validate(['amount' => ['required', 'numeric', 'min:50']]);

        $payout = $service->request($profile, (float) $data['amount']);

        return response()->json([
            'data' => $this->payload($payout),
            'message' => __('Payout requested — Temari.et will process it shortly.'),
        ], 201);
    }

    /** Platform: the payout desk. */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeDesk($request);

        $query = TutorPayout::query()
            ->with(['tutorProfile:id,slug,user_id,wallet_balance', 'tutorProfile.user:id,name,phone'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at');

        $page = $query->paginate(min((int) $request->input('per_page', 25), 100));

        $page->getCollection()->transform(fn (TutorPayout $p) => array_merge($this->payload($p), [
            'tutor_name' => $p->tutorProfile?->user?->name,
            'tutor_phone' => $p->tutorProfile?->user?->phone,
            'wallet_balance' => (string) ($p->tutorProfile?->wallet_balance ?? '0'),
        ]));

        return response()->json($page);
    }

    public function approve(Request $request, TutorPayout $payout, PayoutService $service): JsonResponse
    {
        $this->authorizeDesk($request);

        $payout = $service->approve($payout, $request->user());

        return response()->json(['data' => $this->payload($payout), 'message' => __('Payout approved — funds reserved.')]);
    }

    public function pay(Request $request, TutorPayout $payout, PayoutService $service, ChapaDriver $chapa, Notifier $notifier): JsonResponse
    {
        $this->authorizeDesk($request);

        $data = $request->validate([
            'method' => ['required', Rule::in(['chapa', 'manual'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payout = $data['method'] === 'chapa'
            ? $service->payViaChapa($payout, $request->user(), $chapa)
            : $service->markPaidManually($payout, $request->user(), $data['note'] ?? null);

        $notifier->toUser($payout->tutorProfile?->user, 'tutoring.payout_paid', [
            'amount' => (string) $payout->amount,
        ], ['link' => '/tutoring/earnings']);

        return response()->json(['data' => $this->payload($payout), 'message' => __('Payout sent.')]);
    }

    public function reverse(Request $request, TutorPayout $payout, PayoutService $service): JsonResponse
    {
        $this->authorizeDesk($request);

        $data = $request->validate([
            'status' => ['required', Rule::in([PayoutStatus::Failed->value, PayoutStatus::Canceled->value])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $payout = $service->reverse($payout, $request->user(), $data['status'], $data['reason'] ?? null);

        return response()->json(['data' => $this->payload($payout), 'message' => __('Payout reversed.')]);
    }

    private function authorizeDesk(Request $request): void
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TutorPayout $p): array
    {
        return [
            'id' => $p->id,
            'amount' => $p->amount,
            'method' => $p->method,
            'status' => $p->status->value,
            'bank_name' => $p->bank_name,
            'bank_code' => $p->bank_code,
            'account_number' => $p->account_number,
            'account_name' => $p->account_name,
            'gateway_ref' => $p->gateway_ref,
            'failure_reason' => $p->failure_reason,
            'note' => $p->note,
            'approved_at' => $p->approved_at?->toISOString(),
            'paid_at' => $p->paid_at?->toISOString(),
            'created_at' => $p->created_at?->toISOString(),
        ];
    }
}
