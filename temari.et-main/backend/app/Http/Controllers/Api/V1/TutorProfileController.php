<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentCategory;
use App\Enums\EngagementStatus;
use App\Enums\PayoutStatus;
use App\Enums\TutoringRequestStatus;
use App\Enums\TutoringSessionStatus;
use App\Enums\TutorStatus;
use App\Http\Controllers\Controller;
use App\Models\EmployeeAttachment;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\TutorAttachment;
use App\Models\TutoringRequest;
use App\Models\TutoringSession;
use App\Models\TutorPayout;
use App\Models\TutorProfile;
use App\Support\Languages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The tutor's OWN lane (ADR-012 relationship access: the tutor_profiles row
 * is the credential — no membership, no context headers). Application
 * drafting + submission, verification documents (with the teacher shortcut
 * that imports from the user's own employee file), the payout account and
 * the workspace dashboard.
 */
class TutorProfileController extends Controller
{
    /** My profile (or the empty application scaffold). */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->tutorProfile()
            ->with(['subjects.subject:id,name,code', 'attachments' => fn ($q) => $q->latest()])
            ->first();

        return response()->json(['data' => [
            'profile' => $profile !== null ? $this->profilePayload($profile) : null,
            'languages' => Languages::ALL,
            // The application form's catalogs — applicants hold no staff
            // context, so the platform subject/grade catalogs ship here.
            'subjects' => Subject::query()
                ->whereNull('school_id')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'grade_levels' => GradeLevel::query()
                ->orderBy('sort_order')
                ->get(['id', 'name', 'sort_order']),
        ]]);
    }

    /** Create/update the application. Approved profiles edit business fields only. */
    public function upsert(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->tutorProfile()->first();

        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'hourly_rate' => ['nullable', 'numeric', 'min:20', 'max:10000'],
            'additional_child_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'mode' => ['required', Rule::in(['online', 'in_person', 'both'])],
            'region' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'sub_city' => ['nullable', 'string', 'max:80'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:8'],
            'education_level' => ['nullable', 'string', 'max:60'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'fayda_id' => ['nullable', 'string', 'max:30'],
            'subjects' => ['nullable', 'array', 'max:12'],
            'subjects.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'subjects.*.grade_sorts' => ['nullable', 'array'],
            'subjects.*.grade_sorts.*' => ['integer'],
        ]);

        if ($profile !== null && ! $profile->status->isEditable() && $profile->status !== TutorStatus::Approved) {
            abort(422, 'Your application is under review.');
        }

        $attributes = collect($data)->except(['subjects', 'fayda_id'])->all();

        // Identity fields are frozen once approved — only Temari.et staff
        // change a verified Fayda claim.
        if (($profile === null || $profile->status->isEditable()) && filled($data['fayda_id'] ?? null)) {
            $hash = TutorProfile::hashFayda($data['fayda_id']);

            $claimed = TutorProfile::query()
                ->where('fayda_hash', $hash)
                ->when($profile !== null, fn ($q) => $q->whereKeyNot($profile->id))
                ->exists();

            abort_if($claimed, 422, 'This Fayda ID is already registered with another tutor.');

            $attributes['fayda_id'] = $data['fayda_id'];
            $attributes['fayda_hash'] = $hash;
        }

        $profile = TutorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            $attributes,
        );

        if (isset($data['subjects'])) {
            $keep = [];
            foreach ($data['subjects'] as $row) {
                $keep[] = $profile->subjects()->updateOrCreate(
                    ['subject_id' => $row['subject_id']],
                    ['grade_sorts' => array_values($row['grade_sorts'] ?? [])],
                )->id;
            }
            $profile->subjects()->whereNotIn('id', $keep)->delete();
        }

        // refresh(): a freshly created row must pick up its DB defaults
        // (status draft) before the payload reads them.
        $profile->refresh()->load(['subjects.subject:id,name,code', 'attachments' => fn ($q) => $q->latest()]);

        return response()->json([
            'data' => $this->profilePayload($profile),
            'message' => __('Profile saved.'),
        ]);
    }

    /** Submit the application for Temari.et review. */
    public function submit(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        abort_unless($profile->status->isEditable(), 422, 'This application cannot be submitted right now.');

        $missing = array_keys(array_filter([
            'headline' => blank($profile->headline),
            'bio' => blank($profile->bio),
            'hourly_rate' => $profile->hourly_rate === null,
            'fayda_id' => blank($profile->fayda_hash),
            'subjects' => ! $profile->subjects()->exists(),
            'documents' => ! $profile->attachments()->exists(),
        ]));

        abort_if($missing !== [], 422, 'Complete your application first: '.implode(', ', $missing).'.');

        $profile->update([
            'status' => TutorStatus::Pending->value,
            'submitted_at' => now(),
            'decline_reason' => null,
        ]);

        return response()->json([
            'data' => ['status' => $profile->status->value],
            'message' => __('Application submitted for review.'),
        ]);
    }

    public function storeAttachment(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
        ]);

        $path = $request->file('file')->store(
            "tutor-attachments/{$profile->id}",
            ['disk' => config('filesystems.default')],
        );

        $attachment = $profile->attachments()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'path' => $path,
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
        ]);

        return response()->json([
            'data' => $this->attachmentPayload($attachment),
            'message' => __('Document uploaded.'),
        ], 201);
    }

    public function destroyAttachment(Request $request, TutorAttachment $attachment): JsonResponse
    {
        $profile = $this->ownProfile($request);

        abort_unless($attachment->tutor_profile_id === $profile->id, 404);

        // Imported copies point at the employee file's object — only delete
        // R2 objects we created ourselves.
        if ($attachment->source_employee_attachment_id === null) {
            Storage::disk(config('filesystems.default'))->delete($attachment->path);
        }

        $attachment->forceDelete();

        return response()->json(['message' => __('Document removed.')]);
    }

    /** The teacher shortcut: my employee-file documents, importable. */
    public function employeeAttachments(Request $request): JsonResponse
    {
        $rows = EmployeeAttachment::query()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()
            ->get();

        return response()->json(['data' => $rows->map(fn (EmployeeAttachment $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'mime_type' => $row->mime_type,
            'size' => $row->size,
            'created_at' => $row->created_at,
        ])->values()]);
    }

    public function importEmployeeAttachments(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'attachment_ids' => ['required', 'array', 'min:1', 'max:20'],
            'attachment_ids.*' => ['integer'],
        ]);

        // Strictly the user's OWN employee file — never another person's.
        $sources = EmployeeAttachment::query()
            ->whereIn('id', $data['attachment_ids'])
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->get();

        $imported = $sources->map(function (EmployeeAttachment $source) use ($profile): TutorAttachment {
            return TutorAttachment::query()->firstOrCreate(
                [
                    'tutor_profile_id' => $profile->id,
                    'source_employee_attachment_id' => $source->id,
                ],
                [
                    'name' => $source->name,
                    'path' => $source->path,
                    'mime_type' => $source->mime_type,
                    'size' => $source->size,
                ],
            );
        });

        return response()->json([
            'data' => $imported->map(fn (TutorAttachment $a) => $this->attachmentPayload($a))->values(),
            'message' => __(':count document(s) attached.', ['count' => $imported->count()]),
        ]);
    }

    public function updatePayoutAccount(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'payout_bank_code' => ['required', 'string', 'max:20'],
            'payout_bank_name' => ['required', 'string', 'max:80'],
            'payout_account_number' => ['required', 'string', 'max:40'],
            'payout_account_name' => ['required', 'string', 'max:120'],
        ]);

        $profile->update($data);

        return response()->json(['message' => __('Payout account saved.')]);
    }

    /** The tutor workspace home: one aggregated payload. */
    public function dashboard(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $upcoming = $profile->engagements()
            ->where('status', EngagementStatus::Active->value)
            ->with(['student:id,first_name,father_name', 'payer:id,name'])
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'learner' => $e->learnerName(),
                'subjects' => $e->subjects,
                'sessions_per_week' => $e->sessions_per_week,
            ]);

        return response()->json(['data' => [
            'status' => $profile->status->value,
            'wallet_balance' => (string) $profile->wallet_balance,
            'rating_avg' => $profile->rating_avg,
            'rating_count' => $profile->rating_count,
            'hours_taught' => (string) $profile->hours_taught,
            'boosted_until' => $profile->boosted_until?->toISOString(),
            'pending_requests' => $profile->hasMany(TutoringRequest::class)
                ->where('status', TutoringRequestStatus::Pending->value)->count(),
            'active_engagements' => $upcoming,
            'sessions_awaiting_confirmation' => TutoringSession::query()
                ->whereIn('engagement_id', $profile->engagements()->select('id'))
                ->where('status', TutoringSessionStatus::Logged->value)
                ->count(),
            'open_payout' => $profile->hasMany(TutorPayout::class)
                ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Approved->value])
                ->exists(),
        ]]);
    }

    private function ownProfile(Request $request): TutorProfile
    {
        $profile = $request->user()->tutorProfile()->first();

        abort_if($profile === null, 403, 'Apply as a tutor first.');

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(TutorProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'slug' => $profile->slug,
            'status' => $profile->status->value,
            'headline' => $profile->headline,
            'bio' => $profile->bio,
            'video_url' => $profile->video_url,
            'hourly_rate' => $profile->hourly_rate,
            'additional_child_rate' => $profile->additional_child_rate,
            'mode' => $profile->mode,
            'region' => $profile->region,
            'city' => $profile->city,
            'sub_city' => $profile->sub_city,
            'languages' => $profile->languages ?? [],
            'education_level' => $profile->education_level,
            'experience_years' => $profile->experience_years,
            'has_fayda' => filled($profile->fayda_hash),
            'decline_reason' => $profile->decline_reason,
            'suspend_reason' => $profile->suspend_reason,
            'submitted_at' => $profile->submitted_at?->toISOString(),
            'rating_avg' => $profile->rating_avg,
            'rating_count' => $profile->rating_count,
            'hours_taught' => (string) $profile->hours_taught,
            'wallet_balance' => (string) $profile->wallet_balance,
            'boosted_until' => $profile->boosted_until?->toISOString(),
            'commission_percent' => $profile->effectiveCommissionPercent(),
            'payout_account' => [
                'bank_code' => $profile->payout_bank_code,
                'bank_name' => $profile->payout_bank_name,
                'account_number' => $profile->payout_account_number,
                'account_name' => $profile->payout_account_name,
            ],
            'subjects' => $profile->subjects->map(fn ($row) => [
                'subject_id' => $row->subject_id,
                'name' => $row->subject?->name,
                'code' => $row->subject?->code,
                'grade_sorts' => $row->grade_sorts ?? [],
            ])->values(),
            'attachments' => $profile->attachments->map(fn ($a) => $this->attachmentPayload($a))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentPayload(TutorAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'category' => $attachment->category,
            'url' => $attachment->url(),
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'imported' => $attachment->source_employee_attachment_id !== null,
            'created_at' => $attachment->created_at,
        ];
    }
}
