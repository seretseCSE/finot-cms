<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A curated question pool (ADR-016). Platform banks (`school_id` null) hold
 * the national past-paper/exam-prep content; school banks (`school_id` set,
 * optional `branch_id`) hold the school's own material.
 */
#[Fillable([
    'school_id', 'branch_id', 'name', 'description', 'subject_id',
    'grade_level_id', 'topics', 'is_active', 'created_by',
])]
class QuestionBank extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * File a topic into the bank's chapter list the first time a question
     * uses it — keeps the curated list and the actual content in sync.
     */
    public function rememberTopic(?string $topic): void
    {
        $topic = trim((string) $topic);
        if ($topic === '') {
            return;
        }

        $topics = $this->topics ?? [];
        foreach ($topics as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($topic)) {
                return;
            }
        }

        $this->update(['topics' => [...$topics, $topic]]);
    }

    public function isPlatform(): bool
    {
        return $this->school_id === null;
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<GradeLevel, $this> */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
