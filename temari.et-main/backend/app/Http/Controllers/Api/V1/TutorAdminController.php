<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TutorStatus;
use App\Http\Controllers\Controller;
use App\Models\TutorProfile;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Temari.et staff vetting of tutors (`tutors.review`): the review queue +
 * register, the full application detail (including the DECRYPTED Fayda —
 * the reviewer must check it against the uploaded ID until the Fayda API
 * verifies automatically), and the decisions. Every decision is
 * activity-logged and notified to the applicant.
 */
class TutorAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeReview($request);

        $query = TutorProfile::query()
            ->with(['user:id,name,phone,email', 'subjects.subject:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->tap(fn ($q) => SearchTerm::apply($q, $request->string('search')->trim()->value(), fn ($w, string $n) => $w
                ->where('headline', 'ilike', SearchTerm::contains($n))
                ->orWhereHas('user', fn ($u) => $u->where('search_text', 'ilike', SearchTerm::contains($n)))))
            // Review queue first-in-first-out; register newest-first.
            ->when(
                $request->string('status')->toString() === TutorStatus::Pending->value,
                fn ($q) => $q->orderBy('submitted_at'),
                fn ($q) => $q->orderByDesc('created_at'),
            );

        $page = $query->paginate(min((int) $request->input('per_page', 25), 100));

        $page->getCollection()->transform(fn (TutorProfile $t) => [
            'id' => $t->id,
            'name' => $t->user?->name,
            'phone' => $t->user?->phone,
            'headline' => $t->headline,
            'status' => $t->status->value,
            'city' => $t->city,
            'mode' => $t->mode,
            'hourly_rate' => $t->hourly_rate,
            'rating_avg' => $t->rating_avg,
            'rating_count' => $t->rating_count,
            'hours_taught' => (string) $t->hours_taught,
            'wallet_balance' => (string) $t->wallet_balance,
            'subjects' => $t->subjects->map(fn ($s) => $s->subject?->name)->filter()->values(),
            'submitted_at' => $t->submitted_at?->toISOString(),
            'created_at' => $t->created_at?->toISOString(),
        ]);

        return response()->json($page);
    }

    public function show(Request $request, TutorProfile $tutorProfile): JsonResponse
    {
        $this->authorizeReview($request);

        $tutorProfile->load([
            'user:id,name,phone,email,avatar_path',
            'subjects.subject:id,name,code',
            'attachments' => fn ($q) => $q->latest(),
            'reviewedBy:id,name',
        ]);

        return response()->json(['data' => [
            'id' => $tutorProfile->id,
            'slug' => $tutorProfile->slug,
            'status' => $tutorProfile->status->value,
            'name' => $tutorProfile->user?->name,
            'phone' => $tutorProfile->user?->phone,
            'email' => $tutorProfile->user?->email,
            'avatar_url' => $tutorProfile->user?->avatarUrl(),
            'headline' => $tutorProfile->headline,
            'bio' => $tutorProfile->bio,
            'video_url' => $tutorProfile->video_url,
            'hourly_rate' => $tutorProfile->hourly_rate,
            'additional_child_rate' => $tutorProfile->additional_child_rate,
            'mode' => $tutorProfile->mode,
            'region' => $tutorProfile->region,
            'city' => $tutorProfile->city,
            'sub_city' => $tutorProfile->sub_city,
            'languages' => $tutorProfile->languages ?? [],
            'education_level' => $tutorProfile->education_level,
            'experience_years' => $tutorProfile->experience_years,
            // Reviewer-only: the decrypted Fayda for manual vetting.
            'fayda_id' => $tutorProfile->fayda_id,
            'commission_percent' => $tutorProfile->commission_percent,
            'effective_commission_percent' => $tutorProfile->effectiveCommissionPercent(),
            'rating_avg' => $tutorProfile->rating_avg,
            'rating_count' => $tutorProfile->rating_count,
            'hours_taught' => (string) $tutorProfile->hours_taught,
            'wallet_balance' => (string) $tutorProfile->wallet_balance,
            'boosted_until' => $tutorProfile->boosted_until?->toISOString(),
            'decline_reason' => $tutorProfile->decline_reason,
            'suspend_reason' => $tutorProfile->suspend_reason,
            'submitted_at' => $tutorProfile->submitted_at?->toISOString(),
            'reviewed_at' => $tutorProfile->reviewed_at?->toISOString(),
            'reviewed_by_name' => $tutorProfile->reviewedBy?->name,
            'subjects' => $tutorProfile->subjects->map(fn ($row) => [
                'subject_id' => $row->subject_id,
                'name' => $row->subject?->name,
                'grade_sorts' => $row->grade_sorts ?? [],
            ])->values(),
            'attachments' => $tutorProfile->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'category' => $a->category,
                'url' => $a->url(),
                'mime_type' => $a->mime_type,
                'size' => $a->size,
                'imported' => $a->source_employee_attachment_id !== null,
                'created_at' => $a->created_at,
            ])->values(),
        ]]);
    }

    public function approve(Request $request, TutorProfile $tutorProfile, Notifier $notifier): JsonResponse
    {
        $this->authorizeReview($request);

        abort_unless($tutorProfile->status === TutorStatus::Pending, 422, 'Only pending applications can be approved.');

        $tutorProfile->update([
            'status' => TutorStatus::Approved->value,
            'slug' => $tutorProfile->slug ?? $tutorProfile->allocateSlug(),
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'decline_reason' => null,
        ]);

        ActivityLogger::log($request->user(), 'tutor.approved', $tutorProfile);

        $notifier->toUser($tutorProfile->user, 'tutoring.application_approved', [], ['link' => '/tutoring']);

        return response()->json([
            'data' => ['status' => $tutorProfile->status->value, 'slug' => $tutorProfile->slug],
            'message' => __('Tutor approved.'),
        ]);
    }

    public function decline(Request $request, TutorProfile $tutorProfile, Notifier $notifier): JsonResponse
    {
        $this->authorizeReview($request);

        abort_unless($tutorProfile->status === TutorStatus::Pending, 422, 'Only pending applications can be declined.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $tutorProfile->update([
            'status' => TutorStatus::Declined->value,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'decline_reason' => $data['reason'],
        ]);

        ActivityLogger::log($request->user(), 'tutor.declined', $tutorProfile, ['reason' => $data['reason']]);

        $notifier->toUser($tutorProfile->user, 'tutoring.application_declined', [
            'reason' => $data['reason'],
        ], ['link' => '/tutoring/apply']);

        return response()->json(['message' => __('Application declined.')]);
    }

    public function suspend(Request $request, TutorProfile $tutorProfile, Notifier $notifier): JsonResponse
    {
        $this->authorizeReview($request);

        abort_unless($tutorProfile->status === TutorStatus::Approved, 422, 'Only approved tutors can be suspended.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $tutorProfile->update([
            'status' => TutorStatus::Suspended->value,
            'suspend_reason' => $data['reason'],
        ]);

        ActivityLogger::log($request->user(), 'tutor.suspended', $tutorProfile, ['reason' => $data['reason']]);

        $notifier->toUser($tutorProfile->user, 'tutoring.profile_suspended', [
            'reason' => $data['reason'],
        ], ['link' => '/tutoring']);

        return response()->json(['message' => __('Tutor suspended.')]);
    }

    public function reinstate(Request $request, TutorProfile $tutorProfile): JsonResponse
    {
        $this->authorizeReview($request);

        abort_unless($tutorProfile->status === TutorStatus::Suspended, 422, 'Only suspended tutors can be reinstated.');

        $tutorProfile->update([
            'status' => TutorStatus::Approved->value,
            'suspend_reason' => null,
        ]);

        ActivityLogger::log($request->user(), 'tutor.reinstated', $tutorProfile);

        return response()->json(['message' => __('Tutor reinstated.')]);
    }

    /** Per-tutor commission override (null = platform default). */
    public function setCommission(Request $request, TutorProfile $tutorProfile): JsonResponse
    {
        abort_unless($request->user()?->hasPlatformPermission('marketplace.manage'), 403);

        $data = $request->validate([
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);

        $tutorProfile->update(['commission_percent' => $data['commission_percent']]);

        ActivityLogger::log($request->user(), 'tutor.commission_set', $tutorProfile, $data);

        return response()->json(['message' => __('Commission updated.')]);
    }

    private function authorizeReview(Request $request): void
    {
        abort_unless($request->user()?->hasPlatformPermission('tutors.review'), 403);
    }
}
