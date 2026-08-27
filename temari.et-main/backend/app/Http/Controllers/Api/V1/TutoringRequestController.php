<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptTutoringRequestAction;
use App\Enums\TutoringRequestStatus;
use App\Enums\TutorStatus;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TutoringRequest;
use App\Models\TutorProfile;
use App\Services\Notify\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Hire requests, both sides. Family lane: send to an approved tutor (for a
 * guardian-linked child or yourself), track, withdraw. Tutor lane: inbox +
 * accept (creates the engagement + first escrow cycle) / decline. Access is
 * pure relationship (requester or profile owner) — no memberships.
 */
class TutoringRequestController extends Controller
{
    /** Family: send a request to one tutor. */
    public function store(Request $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'tutor_slug' => ['required', 'string'],
            'student_id' => ['nullable', 'integer'],
            'subject_ids' => ['required', 'array', 'min:1', 'max:6'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'grade_label' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
            'mode' => ['required', Rule::in(['online', 'in_person'])],
            'sessions_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'hours_per_session' => ['required', 'numeric', 'min:0.5', 'max:4'],
        ]);

        $tutor = TutorProfile::query()
            ->where('slug', $data['tutor_slug'])
            ->where('status', TutorStatus::Approved->value)
            ->firstOrFail();

        abort_if($tutor->user_id === $request->user()->id, 422, 'You cannot hire yourself.');

        // The learner: a guardian-linked child, or the requester themself.
        if (filled($data['student_id'] ?? null)) {
            $isGuardian = Student::query()
                ->whereKey((int) $data['student_id'])
                ->whereHas('guardians', fn ($q) => $q->whereHas(
                    'parentProfile',
                    fn ($p) => $p->where('user_id', $request->user()->id),
                ))
                ->exists();

            abort_unless($isGuardian, 403, 'You may only request tutoring for your own children.');
        }

        $open = TutoringRequest::query()
            ->where('tutor_profile_id', $tutor->id)
            ->where('requester_user_id', $request->user()->id)
            ->where('student_id', $data['student_id'] ?? null)
            ->where('status', TutoringRequestStatus::Pending->value)
            ->exists();

        abort_if($open, 422, 'You already have a pending request with this tutor.');

        $tutoringRequest = TutoringRequest::create([
            'tutor_profile_id' => $tutor->id,
            'requester_user_id' => $request->user()->id,
            'student_id' => $data['student_id'] ?? null,
            'subject_ids' => $data['subject_ids'],
            'grade_label' => $data['grade_label'] ?? null,
            'message' => $data['message'] ?? null,
            'mode' => $data['mode'],
            'sessions_per_week' => $data['sessions_per_week'],
            'hours_per_session' => $data['hours_per_session'],
        ]);

        $notifier->toUser($tutor->user, 'tutoring.request_received', [
            'name' => $request->user()->name,
        ], ['link' => '/tutoring/requests', 'dedupeKey' => 'tutoring.request_received']);

        return response()->json([
            'data' => ['id' => $tutoringRequest->id],
            'message' => __('Request sent. The tutor will respond soon.'),
        ], 201);
    }

    /** Family: my sent requests. */
    public function mine(Request $request): JsonResponse
    {
        $rows = TutoringRequest::query()
            ->where('requester_user_id', $request->user()->id)
            ->with(['tutorProfile:id,slug,headline,hourly_rate,user_id', 'tutorProfile.user:id,name,avatar_path', 'student:id,first_name,father_name'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows->map(fn (TutoringRequest $r) => [
            'id' => $r->id,
            'status' => $r->status->value,
            'tutor' => [
                'slug' => $r->tutorProfile?->slug,
                'name' => $r->tutorProfile?->user?->name,
                'avatar_url' => $r->tutorProfile?->user?->avatarUrl(),
                'headline' => $r->tutorProfile?->headline,
                'hourly_rate' => $r->tutorProfile?->hourly_rate,
            ],
            'student_name' => $r->student !== null ? trim($r->student->first_name.' '.$r->student->father_name) : null,
            'mode' => $r->mode,
            'sessions_per_week' => $r->sessions_per_week,
            'hours_per_session' => $r->hours_per_session,
            'message' => $r->message,
            'response_note' => $r->response_note,
            'created_at' => $r->created_at?->toISOString(),
            'responded_at' => $r->responded_at?->toISOString(),
        ])->values()]);
    }

    public function withdraw(Request $request, TutoringRequest $tutoringRequest): JsonResponse
    {
        abort_unless($tutoringRequest->requester_user_id === $request->user()->id, 404);
        abort_unless($tutoringRequest->status === TutoringRequestStatus::Pending, 422, 'Only pending requests can be withdrawn.');

        $tutoringRequest->update([
            'status' => TutoringRequestStatus::Withdrawn->value,
            'responded_at' => now(),
        ]);

        return response()->json(['message' => __('Request withdrawn.')]);
    }

    /** Tutor: my inbox. */
    public function inbox(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $rows = TutoringRequest::query()
            ->where('tutor_profile_id', $profile->id)
            ->with(['requester:id,name,avatar_path', 'student:id,first_name,father_name'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(100)
            ->get();

        $subjectNames = Subject::query()
            ->whereIn('id', $rows->flatMap(fn ($r) => $r->subject_ids ?? [])->unique())
            ->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn (TutoringRequest $r) => [
            'id' => $r->id,
            'status' => $r->status->value,
            'requester_name' => $r->requester?->name,
            'requester_avatar_url' => $r->requester?->avatarUrl(),
            'student_name' => $r->student !== null ? trim($r->student->first_name.' '.$r->student->father_name) : null,
            'subjects' => collect($r->subject_ids ?? [])->map(fn ($id) => $subjectNames[$id] ?? null)->filter()->values(),
            'grade_label' => $r->grade_label,
            'mode' => $r->mode,
            'sessions_per_week' => $r->sessions_per_week,
            'hours_per_session' => $r->hours_per_session,
            'message' => $r->message,
            'created_at' => $r->created_at?->toISOString(),
        ])->values()]);
    }

    /** Tutor: accept → engagement + first cycle. */
    public function accept(
        Request $request,
        TutoringRequest $tutoringRequest,
        AcceptTutoringRequestAction $action,
        Notifier $notifier,
    ): JsonResponse {
        $profile = $this->ownProfile($request);

        abort_unless($tutoringRequest->tutor_profile_id === $profile->id, 404);

        $engagement = $action->execute($tutoringRequest);

        $notifier->toUser($tutoringRequest->requester, 'tutoring.request_accepted', [
            'name' => $request->user()->name,
        ], ['link' => '/me/tutoring']);

        return response()->json([
            'data' => ['engagement_id' => $engagement->id],
            'message' => __('Request accepted — the engagement is live once the family pays the first month.'),
        ]);
    }

    /** Tutor: decline with an optional note. */
    public function decline(Request $request, TutoringRequest $tutoringRequest, Notifier $notifier): JsonResponse
    {
        $profile = $this->ownProfile($request);

        abort_unless($tutoringRequest->tutor_profile_id === $profile->id, 404);
        abort_unless($tutoringRequest->status === TutoringRequestStatus::Pending, 422, 'This request was already decided.');

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $tutoringRequest->update([
            'status' => TutoringRequestStatus::Declined->value,
            'responded_at' => now(),
            'response_note' => $data['note'] ?? null,
        ]);

        $notifier->toUser($tutoringRequest->requester, 'tutoring.request_declined', [
            'name' => $request->user()->name,
        ], ['link' => '/me/tutoring']);

        return response()->json(['message' => __('Request declined.')]);
    }

    private function ownProfile(Request $request): TutorProfile
    {
        $profile = $request->user()->tutorProfile()->first();

        abort_if($profile === null, 403);

        return $profile;
    }
}
