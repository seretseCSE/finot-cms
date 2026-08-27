<?php

namespace App\Models;

use App\Enums\EngagementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tutoring contract with snapshotted terms (see the migration).
 * planned monthly hours ≈ sessions_per_week × hours_per_session × 4.
 */
#[Fillable([
    'tutor_profile_id', 'payer_user_id', 'student_id', 'request_id', 'subjects',
    'grade_label', 'mode', 'sessions_per_week', 'hours_per_session', 'hourly_rate',
    'commission_percent', 'status', 'started_on', 'ended_on', 'end_reason',
    'ended_by', 'conversation_id',
])]
class TutoringEngagement extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EngagementStatus::class,
            'subjects' => 'array',
            'hours_per_session' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'started_on' => 'date',
            'ended_on' => 'date',
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
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<TutoringCycle, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(TutoringCycle::class, 'engagement_id');
    }

    /**
     * @return HasMany<TutoringSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TutoringSession::class, 'engagement_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** The default monthly hour budget a new cycle bills. */
    public function plannedMonthlyHours(): float
    {
        return round($this->sessions_per_week * (float) $this->hours_per_session * 4, 2);
    }

    /** The learner's display name (a child student or the payer themself). */
    public function learnerName(): string
    {
        if ($this->student !== null) {
            return trim($this->student->first_name.' '.$this->student->father_name);
        }

        return trim(($this->payer->first_name ?? '').' '.($this->payer->father_name ?? ''));
    }
}
