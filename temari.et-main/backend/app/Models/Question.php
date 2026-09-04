<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One question (ADR-016). `body` is the type-shaped content shown to takers;
 * `answer_key` (correct answers / tolerance / keyword rubric) NEVER leaves
 * the server for takers — resources must strip it. Referenced questions are
 * retired, never deleted.
 */
#[Fillable([
    'question_bank_id', 'parent_id', 'position', 'type', 'body', 'answer_key', 'points',
    'difficulty', 'topic', 'tags', 'source', 'explanation', 'status', 'created_by',
])]
class Question extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'published', 'retired'];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'body' => 'array',
            'answer_key' => 'array',
            'tags' => 'array',
            'points' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<QuestionBank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The group container this sub-question belongs to, if any. */
    /** @return BelongsTo<Question, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** A group's sub-questions in their authored order. */
    /** @return HasMany<Question, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /**
     * The body as clients consume it: R2-stored media (`path`) gains a
     * short-lived signed `url`. Used by BOTH the staff resource and the
     * taker paper — attachments are part of the question for everyone.
     */
    public function presentBody(): array
    {
        $body = QuestionRules::hydrateBodyMedia($this->body ?? []);

        if (isset($body['attachments']) && is_array($body['attachments'])) {
            $body['attachments'] = array_values(array_map(function ($file): array {
                if (is_array($file) && isset($file['path'])) {
                    $file['url'] = s3Url($file['path']);
                }

                return is_array($file) ? $file : [];
            }, $body['attachments']));
        }

        return $body;
    }

    /** Whether any quiz references this question (retire instead of delete). */
    public function isReferenced(): bool
    {
        return QuizQuestion::query()->where('question_id', $this->id)->exists()
            || QuizAttemptAnswer::query()->where('question_id', $this->id)->exists();
    }
}
