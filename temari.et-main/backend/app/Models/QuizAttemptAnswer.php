<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer per question per attempt. Manual scores win over auto scores;
 * `ai_score` is reserved for the AI-grading phase.
 */
#[Fillable([
    'quiz_attempt_id', 'question_id', 'answer', 'auto_score', 'manual_score',
    'feedback', 'graded_by', 'answered_at',
])]
class QuizAttemptAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'auto_score' => 'decimal:2',
            'manual_score' => 'decimal:2',
            'ai_score' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }

    /** The score that counts: human override, else machine. */
    public function effectiveScore(): ?float
    {
        return $this->manual_score !== null
            ? (float) $this->manual_score
            : ($this->auto_score !== null ? (float) $this->auto_score : null);
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
