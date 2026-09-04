<?php

namespace App\Models;

use App\Enums\QuizAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One sitting of a quiz (ADR-016). `deadline_at` (stamped at start) is the
 * only clock that counts; `question_ids` freezes this sitting's shuffled
 * paper so resume survives reconnects; integrity events accumulate as
 * review flags, never auto-fails.
 */
#[Fillable([
    'quiz_id', 'user_id', 'student_id', 'student_enrollment_id',
    'attempt_number', 'status', 'started_at', 'deadline_at', 'seed',
    'question_ids', 'max_score', 'token_hash',
])]
class QuizAttempt extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => QuizAttemptStatus::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'seed' => 'integer',
            'attempt_number' => 'integer',
            'question_ids' => 'array',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'pending_manual' => 'boolean',
            'integrity_log' => 'array',
            'flag_count' => 'integer',
        ];
    }

    /** Seconds left on the attempt clock (0 when expired or clockless). */
    public function remainingSeconds(): ?int
    {
        if ($this->deadline_at === null) {
            return null;
        }

        return max(0, now()->diffInSeconds($this->deadline_at, false));
    }

    public function isExpired(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast();
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return HasMany<QuizAttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }
}
