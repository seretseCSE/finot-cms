<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CycleStatus;
use App\Http\Controllers\Controller;
use App\Models\TutoringCycle;
use App\Models\TutorReview;
use App\Services\Notify\Notifier;
use App\Services\Tutoring\TutorRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ratings, earned on RELEASED cycles only (the Upwork rule): the family
 * rates the tutor publicly (profile aggregate via TutorRating); the tutor
 * rates the family privately. One review per cycle per direction.
 */
class TutorReviewController extends Controller
{
    public function store(Request $request, TutoringCycle $cycle, TutorRating $rating, Notifier $notifier): JsonResponse
    {
        abort_unless($cycle->status === CycleStatus::Released, 422, 'You can review once the month is completed and settled.');

        $engagement = $cycle->engagement;
        $user = $request->user();

        $direction = match (true) {
            $engagement->payer_user_id === $user->id => TutorReview::FAMILY_TO_TUTOR,
            $user->tutorProfile()->value('id') === $engagement->tutor_profile_id => TutorReview::TUTOR_TO_FAMILY,
            default => abort(404),
        };

        $exists = TutorReview::query()
            ->where('cycle_id', $cycle->id)
            ->where('direction', $direction)
            ->exists();

        abort_if($exists, 422, 'You already reviewed this month.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = TutorReview::create([
            'tutor_profile_id' => $engagement->tutor_profile_id,
            'engagement_id' => $engagement->id,
            'cycle_id' => $cycle->id,
            'reviewer_user_id' => $user->id,
            'direction' => $direction,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'is_public' => $direction === TutorReview::FAMILY_TO_TUTOR,
        ]);

        if ($direction === TutorReview::FAMILY_TO_TUTOR) {
            $rating->recompute($engagement->tutorProfile);

            $notifier->toUser($engagement->tutorProfile?->user, 'tutoring.review_received', [
                'rating' => (string) $data['rating'],
            ], ['link' => '/tutoring', 'dedupeKey' => 'tutoring.review_received']);
        }

        return response()->json([
            'data' => ['id' => $review->id],
            'message' => __('Thank you — your review is saved.'),
        ], 201);
    }
}
