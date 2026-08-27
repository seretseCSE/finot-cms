<?php

namespace App\Actions;

use App\Enums\EngagementStatus;
use App\Enums\TutoringRequestStatus;
use App\Enums\TutorStatus;
use App\Models\Subject;
use App\Models\TutoringEngagement;
use App\Models\TutoringRequest;
use App\Services\Tutoring\CycleBiller;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Tutor accepts a hire request → the contract is born: engagement with
 * SNAPSHOTTED terms (rate + commission frozen now), the first Ethiopian-month
 * escrow cycle (awaiting the family's payment — sessions can't start before
 * funding), and the distinct-learner counter. The chat thread is provisioned
 * lazily by the messaging endpoint.
 */
class AcceptTutoringRequestAction
{
    public function __construct(private readonly CycleBiller $biller) {}

    public function execute(TutoringRequest $request): TutoringEngagement
    {
        return DB::transaction(function () use ($request): TutoringEngagement {
            $locked = TutoringRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TutoringRequestStatus::Pending) {
                throw new HttpException(422, 'This request was already decided.');
            }

            $profile = $locked->tutorProfile;

            if ($profile->status !== TutorStatus::Approved || $profile->hourly_rate === null) {
                throw new HttpException(422, 'Your profile must be approved first.');
            }

            $subjects = Subject::query()
                ->whereIn('id', $locked->subject_ids ?? [])
                ->get(['id', 'name'])
                ->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name])
                ->values()
                ->all();

            $engagement = TutoringEngagement::create([
                'tutor_profile_id' => $profile->id,
                'payer_user_id' => $locked->requester_user_id,
                'student_id' => $locked->student_id,
                'request_id' => $locked->id,
                'subjects' => $subjects,
                'grade_label' => $locked->grade_label,
                'mode' => $locked->mode,
                'sessions_per_week' => $locked->sessions_per_week,
                'hours_per_session' => $locked->hours_per_session,
                'hourly_rate' => $profile->hourly_rate,
                'commission_percent' => $profile->effectiveCommissionPercent(),
                'status' => EngagementStatus::Active->value,
                'started_on' => now('Africa/Addis_Ababa')->toDateString(),
            ]);

            $locked->update([
                'status' => TutoringRequestStatus::Accepted->value,
                'responded_at' => now(),
            ]);

            $this->biller->ensureCycleFor($engagement);

            // Distinct learners over all time (single writer: here;
            // aggregates are non-fillable by design → forceFill).
            $profile->forceFill([
                'students_count' => TutoringEngagement::query()
                    ->where('tutor_profile_id', $profile->id)
                    ->distinct()
                    ->count(DB::raw("COALESCE(student_id::text, 'u'||payer_user_id::text)")),
            ])->save();

            ActivityLogger::log($profile->user, 'tutoring_request.accepted', $engagement);

            return $engagement;
        });
    }
}
