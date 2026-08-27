<?php

namespace App\Models;

use App\Enums\TutoringRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hire request to one tutor (see the migration). Accepting creates the
 * engagement; the request stays as the negotiation record.
 */
#[Fillable([
    'tutor_profile_id', 'requester_user_id', 'student_id', 'subject_ids',
    'grade_label', 'message', 'mode', 'sessions_per_week', 'hours_per_session',
    'status', 'responded_at', 'response_note',
])]
class TutoringRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TutoringRequestStatus::class,
            'subject_ids' => 'array',
            'hours_per_session' => 'decimal:2',
            'responded_at' => 'datetime',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
