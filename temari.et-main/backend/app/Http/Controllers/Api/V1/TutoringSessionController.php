<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CycleStatus;
use App\Enums\EngagementStatus;
use App\Enums\TutoringSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\TutoringEngagement;
use App\Models\TutoringSession;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use App\Support\Meetings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sessions inside a funded cycle. Tutor schedules + logs; family confirms
 * or disputes (auto-confirm after 72h — tutoring:auto-confirm); disputes go
 * to the Temari.et console. A session must land inside its cycle's month
 * and the cycle must be FUNDED — no free lessons on an unpaid month.
 */
class TutoringSessionController extends Controller
{
    public function index(Request $request, TutoringEngagement $engagement): JsonResponse
    {
        $this->accessAs($request, $engagement);

        $rows = $engagement->sessions()
            ->when($request->filled('cycle_id'), fn ($q) => $q->where('cycle_id', $request->integer('cycle_id')))
            ->orderByDesc('scheduled_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows->map(fn (TutoringSession $s) => $this->payload($s))->values()]);
    }

    /** Tutor: schedule a session (online rooms get a meeting link). */
    public function store(Request $request, TutoringEngagement $engagement, Notifier $notifier): JsonResponse
    {
        abort_unless($this->accessAs($request, $engagement) === 'tutor', 403);
        abort_unless($engagement->status === EngagementStatus::Active, 422, 'The engagement is not active.');

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:-1 day', 'before:+62 days'],
            'duration_hours' => ['required', 'numeric', 'min:0.5', 'max:4'],
            'topic' => ['nullable', 'string', 'max:160'],
            'online' => ['sometimes', 'boolean'],
        ]);

        $when = CarbonImmutable::parse($data['scheduled_at'], 'Africa/Addis_Ababa');

        $cycle = $engagement->cycles()
            ->where('starts_on', '<=', $when->toDateString())
            ->where('ends_on', '>=', $when->toDateString())
            ->first();

        abort_if($cycle === null, 422, 'No billing month covers that date yet.');
        abort_unless($cycle->status === CycleStatus::Funded, 422, 'That month is not paid yet — sessions unlock once the family pays.');

        $session = TutoringSession::create([
            'cycle_id' => $cycle->id,
            'engagement_id' => $engagement->id,
            'scheduled_at' => $when,
            'duration_hours' => $data['duration_hours'],
            'topic' => $data['topic'] ?? null,
            'status' => TutoringSessionStatus::Scheduled->value,
        ]);

        if (($data['online'] ?? $engagement->mode !== 'in_person')) {
            $session->update(['meeting_url' => Meetings::roomUrl($engagement->id, $session->id)]);
        }

        $notifier->toUser($engagement->payer, 'tutoring.session_scheduled', [
            'when' => $when->format('M j, H:i'),
        ], ['link' => '/me/tutoring/'.$engagement->id, 'dedupeKey' => 'tutoring.session_scheduled:'.$engagement->id]);

        return response()->json(['data' => $this->payload($session), 'message' => __('Session scheduled.')], 201);
    }

    /** Tutor: log the session as delivered (starts the confirmation clock). */
    public function log(Request $request, TutoringSession $session, Notifier $notifier): JsonResponse
    {
        $engagement = $session->engagement;
        abort_unless($this->accessAs($request, $engagement) === 'tutor', 403);
        abort_unless($session->status === TutoringSessionStatus::Scheduled, 422, 'Only scheduled sessions can be logged.');
        abort_if($session->scheduled_at->isFuture(), 422, 'You cannot log a session before it happens.');

        $data = $request->validate([
            'duration_hours' => ['sometimes', 'numeric', 'min:0.25', 'max:4'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $session->update([
            'status' => TutoringSessionStatus::Logged->value,
            'logged_at' => now(),
            'duration_hours' => $data['duration_hours'] ?? $session->duration_hours,
            'notes' => $data['notes'] ?? $session->notes,
        ]);

        $notifier->toUser($engagement->payer, 'tutoring.session_logged', [
            'hours' => (string) $session->duration_hours,
        ], ['link' => '/me/tutoring/'.$engagement->id, 'dedupeKey' => 'tutoring.session_logged:'.$engagement->id]);

        return response()->json(['data' => $this->payload($session), 'message' => __('Session logged — the family has 72 hours to confirm.')]);
    }

    /** Tutor or family: cancel an upcoming session. */
    public function cancel(Request $request, TutoringSession $session): JsonResponse
    {
        $this->accessAs($request, $session->engagement);
        abort_unless($session->status === TutoringSessionStatus::Scheduled, 422, 'Only scheduled sessions can be canceled.');

        $session->update(['status' => TutoringSessionStatus::Canceled->value]);

        return response()->json(['message' => __('Session canceled.')]);
    }

    /** Family: confirm the lesson happened. */
    public function confirm(Request $request, TutoringSession $session): JsonResponse
    {
        abort_unless($this->accessAs($request, $session->engagement) === 'family', 403);
        abort_unless($session->status === TutoringSessionStatus::Logged, 422, 'Only logged sessions can be confirmed.');

        $session->update([
            'status' => TutoringSessionStatus::Confirmed->value,
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
        ]);

        return response()->json(['message' => __('Session confirmed.')]);
    }

    /** Family: dispute a logged session (freezes only its value). */
    public function dispute(Request $request, TutoringSession $session, Notifier $notifier): JsonResponse
    {
        abort_unless($this->accessAs($request, $session->engagement) === 'family', 403);
        abort_unless($session->status === TutoringSessionStatus::Logged, 422, 'Only logged sessions can be disputed.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $session->update([
            'status' => TutoringSessionStatus::Disputed->value,
            'dispute_reason' => $data['reason'],
            'disputed_at' => now(),
        ]);

        ActivityLogger::log($request->user(), 'tutoring_session.disputed', $session, ['reason' => $data['reason']]);

        $notifier->toUser($session->engagement->tutorProfile?->user, 'tutoring.session_disputed', [], [
            'link' => '/tutoring/engagements/'.$session->engagement_id,
        ]);

        return response()->json(['message' => __('Dispute filed — Temari.et will review it.')]);
    }

    /** Platform: resolve a dispute (upheld = value canceled). */
    public function resolve(Request $request, TutoringSession $session): JsonResponse
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);
        abort_unless($session->status === TutoringSessionStatus::Disputed, 422, 'This session is not disputed.');

        $data = $request->validate(['resolution' => ['required', Rule::in(['upheld', 'rejected'])]]);

        $session->update([
            'status' => $data['resolution'] === 'upheld'
                ? TutoringSessionStatus::Canceled->value
                : TutoringSessionStatus::Confirmed->value,
            'confirmed_at' => $data['resolution'] === 'rejected' ? now() : null,
            'resolution' => $data['resolution'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        ActivityLogger::log($request->user(), 'tutoring_session.resolved', $session, $data);

        return response()->json(['message' => __('Dispute resolved.')]);
    }

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
    private function payload(TutoringSession $s): array
    {
        return [
            'id' => $s->id,
            'cycle_id' => $s->cycle_id,
            'engagement_id' => $s->engagement_id,
            'scheduled_at' => $s->scheduled_at?->toISOString(),
            'duration_hours' => $s->duration_hours,
            'topic' => $s->topic,
            'status' => $s->status->value,
            'meeting_url' => $s->meeting_url,
            'logged_at' => $s->logged_at?->toISOString(),
            'confirmed_at' => $s->confirmed_at?->toISOString(),
            'dispute_reason' => $s->dispute_reason,
            'resolution' => $s->resolution,
            'notes' => $s->notes,
        ];
    }
}
