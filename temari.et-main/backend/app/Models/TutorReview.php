<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A rating earned on a released cycle (see the migration). Public profile
 * aggregates are recomputed by TutorRating — never inline.
 */
#[Fillable([
    'tutor_profile_id', 'engagement_id', 'cycle_id', 'reviewer_user_id',
    'direction', 'rating', 'comment', 'is_public',
])]
class TutorReview extends Model
{
    use SoftDeletes;

    public const string FAMILY_TO_TUTOR = 'family_to_tutor';

    public const string TUTOR_TO_FAMILY = 'tutor_to_family';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TutorProfile, $this>
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * @return BelongsTo<TutoringEngagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(TutoringEngagement::class, 'engagement_id');
    }
}
