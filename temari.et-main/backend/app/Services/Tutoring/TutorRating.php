<?php

namespace App\Services\Tutoring;

use App\Models\TutorProfile;
use App\Models\TutorReview;

/**
 * The single writer of the public rating aggregates on tutor_profiles.
 * Recomputes from public family_to_tutor reviews — called after every
 * review write, never inline-incremented.
 */
class TutorRating
{
    public function recompute(TutorProfile $profile): void
    {
        $stats = TutorReview::query()
            ->where('tutor_profile_id', $profile->id)
            ->where('direction', TutorReview::FAMILY_TO_TUTOR)
            ->where('is_public', true)
            ->selectRaw('count(*) as cnt, avg(rating) as avg')
            ->first();

        // Aggregates are non-fillable by design — the single writer forceFills.
        $profile->forceFill([
            'rating_count' => (int) ($stats->cnt ?? 0),
            'rating_avg' => $stats->cnt > 0 ? round((float) $stats->avg, 2) : null,
        ])->save();
    }
}
