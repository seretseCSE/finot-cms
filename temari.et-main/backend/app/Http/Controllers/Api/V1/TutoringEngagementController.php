<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CycleStatus;
use App\Enums\EngagementStatus;
use App\Http\Controllers\Controller;
use App\Models\TutoringEngagement;
use App\Services\Chat\ChatService;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The tutoring contract, both sides. Every read/write is relationship-gated:
 * the tutor (profile owner) or the payer — nobody else, ever. Pausing stops
 * cycle generation; ending is terminal and hands any refund decision to the
 * Temari.et money console.
 */
class TutoringEngagementController extends Controller
{
    /** Both lanes: ?as=tutor|family decides the perspective. */
    public function index(Request $request): JsonResponse
    {
        $as = $request->string('as')->toString() === 'tutor' ? 'tutor' : 'family';

        $query = TutoringEngagement::query()
            ->with([
                'tutorProfile:id,slug,headline,hourly_rate,user_id',
                'tutorProfile.user:id,name,avatar_path',
                'payer:id,name,avatar_path',
                'student:id,first_name,father_name',
            ]);

        if ($as === 'tutor') {
            $profile = $request->user()->tutorProfile()->first();
            abort_if($profile === null, 403);
            $query->where('tutor_profile_id', $profile->id);
        } else {
            $query->where('payer_user_id', $request->user()->id);
        }

        $rows = $query
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows->map(fn (TutoringEngagement $e) => $this->payload($e, $as))->values()]);
    }

    public function show(Request $request, TutoringEngagement $engagement): JsonResponse
    {
        $as = $this->accessAs($request, $engagement);

        $engagement->load([
            'tutorProfile:id,slug,headline,hourly_rate,user_id',
            'tutorProfile.user:id,name,avatar_path,phone',
            'payer:id,name,avatar_path,phone',
            'student:id,first_name,father_name',
            'cycles' => fn ($q) => $q->orderByDesc('starts_on'),
        ]);

        $payload = $this->payload($engagement, $as);
        $payload['cycles'] = $engagement->cycles->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->label,
            'status' => $c->status->value,
            'starts_on' => $c->starts_on->toDateString(),
            'ends_on' => $c->ends_on->toDateString(),
            'planned_hours' => $c->planned_hours,
            'gross_amount' => $c->gross_amount,
            'credit_applied' => $c->credit_applied,
            'amount_due' => $c->amount_due,
            'confirmed_hours' => $c->confirmed_hours,
            'released_amount' => $as === 'tutor' ? $c->released_amount : null,
            'funded_at' => $c->funded_at?->toISOString(),
            'released_at' => $c->released_at?->toISOString(),
        ])->values();

        // Money privacy: the tutor sees their net, the family their bill —
        // the commission split is Temari.et's business with the tutor.
        $payload['contact_visible'] = $engagement->cycles->contains(fn ($c) => $c->status !== CycleStatus::AwaitingPayment);

        if ($payload['contact_visible']) {
            $payload['tutor_phone'] = $engagement->tutorProfile?->user?->phone;
            $payload['payer_phone'] = $as === 'tutor' ? $engagement->payer?->phone : null;
        }

        return response()->json(['data' => $payload]);
    }

    public function pause(Request $request, TutoringEngagement $engagement): JsonResponse
    {
        $this->accessAs($request, $engagement);

        abort_unless($engagement->status === EngagementStatus::Active, 422, 'Only active engagements can be paused.');

        $engagement->update(['status' => EngagementStatus::Paused->value]);
        ActivityLogger::log($request->user(), 'tutoring_engagement.paused', $engagement);

        return response()->json(['message' => __('Engagement paused.')]);
    }

    public function resume(Request $request, TutoringEngagement $engagement): JsonResponse
    {
        $this->accessAs($request, $engagement);

        abort_unless($engagement->status === EngagementStatus::Paused, 422, 'Only paused engagements can be resumed.');

        $engagement->update(['status' => EngagementStatus::Active->value]);
        ActivityLogger::log($request->user(), 'tutoring_engagement.resumed', $engagement);

        return response()->json(['message' => __('Engagement resumed.')]);
    }

    public function end(Request $request, TutoringEngagement $engagement, Notifier $notifier): JsonResponse
    {
        $as = $this->accessAs($request, $engagement);

        abort_if($engagement->status === EngagementStatus::Ended, 422, 'Already ended.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $engagement->update([
            'status' => EngagementStatus::Ended->value,
            'ended_on' => now('Africa/Addis_Ababa')->toDateString(),
            'end_reason' => $data['reason'] ?? null,
            'ended_by' => $request->user()->id,
        ]);

        ActivityLogger::log($request->user(), 'tutoring_engagement.ended', $engagement, ['by' => $as]);

        $other = $as === 'tutor' ? $engagement->payer : $engagement->tutorProfile?->user;
        $notifier->toUser($other, 'tutoring.engagement_ended', [
            'name' => $request->user()->name,
        ], ['link' => $as === 'tutor' ? '/me/tutoring' : '/tutoring/engagements']);

        return response()->json(['message' => __('Engagement ended.')]);
    }

    /**
     * The engagement's chat thread (ADR-019 context conversation, platform
     * level — no school tenant). Created lazily on first open; both parties
     * then drive it through their normal chat surfaces.
     */
    public function thread(Request $request, TutoringEngagement $engagement, ChatService $chat): JsonResponse
    {
        $this->accessAs($request, $engagement);

        $conversation = $chat->forContext(
            'tutoring_engagement',
            $engagement->id,
            null,
            null,
            array_filter([$engagement->tutorProfile?->user_id, $engagement->payer_user_id]),
        );

        if ($engagement->conversation_id === null) {
            $engagement->update(['conversation_id' => $conversation->id]);
        }

        return response()->json(['data' => ['conversation_id' => $conversation->id]]);
    }

    /** Resolve the caller's side, 404 for strangers. */
    private function accessAs(Request $request, TutoringEngagement $engagement): string
    {
        $user = $request->user();

        if ($engagement->payer_user_id === $user->id) {
            return 'family';
        }

        if ($user->tutorProfile()->value('id') === $engagement->tutor_profile_id) {
            return 'tutor';
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TutoringEngagement $e, string $as): array
    {
        return [
            'id' => $e->id,
            'status' => $e->status->value,
            'as' => $as,
            'tutor' => [
                'slug' => $e->tutorProfile?->slug,
                'name' => $e->tutorProfile?->user?->name,
                'avatar_url' => $e->tutorProfile?->user?->avatarUrl(),
                'headline' => $e->tutorProfile?->headline,
            ],
            'payer_name' => $e->payer?->name,
            'learner_name' => $e->learnerName(),
            'subjects' => $e->subjects ?? [],
            'grade_label' => $e->grade_label,
            'mode' => $e->mode,
            'sessions_per_week' => $e->sessions_per_week,
            'hours_per_session' => $e->hours_per_session,
            'hourly_rate' => $e->hourly_rate,
            'commission_percent' => $as === 'tutor' ? $e->commission_percent : null,
            'started_on' => $e->started_on?->toDateString(),
            'ended_on' => $e->ended_on?->toDateString(),
            'end_reason' => $e->end_reason,
            'conversation_id' => $e->conversation_id,
        ];
    }
}
