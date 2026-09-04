<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fixed question pick inside a quiz; `points` overrides the bank default. */
#[Fillable(['quiz_id', 'question_id', 'points', 'sort_order', 'part_index'])]
class QuizQuestion extends Model
{
    protected function casts(): array
    {
        return ['points' => 'decimal:2', 'sort_order' => 'integer', 'part_index' => 'integer'];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** The points this question is worth inside this quiz. */
    public function effectivePoints(): float
    {
        return (float) ($this->points ?? $this->question?->points ?? 1);
    }
}
