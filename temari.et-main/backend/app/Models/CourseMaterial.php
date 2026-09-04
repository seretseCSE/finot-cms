<?php

namespace App\Models;

use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A learning material (ADR-016) — one row of truth, audience via targets
 * (teacher → specific classes) or the subject + grade window (leadership /
 * platform posts, no target rows).
 */
#[Fillable([
    'school_id', 'branch_id', 'subject_id', 'min_grade_sort', 'max_grade_sort',
    'title', 'description', 'type', 'content', 'is_pinned', 'is_active', 'created_by',
])]
class CourseMaterial extends Model
{
    use SoftDeletes;

    public const TYPES = ['file', 'link', 'youtube', 'text'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'min_grade_sort' => 'integer',
            'max_grade_sort' => 'integer',
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Rich description with stored `<img data-path>` markers re-signed. */
    public function presentDescription(): ?string
    {
        return $this->description === null
            ? null
            : QuestionRules::hydrateStemMedia($this->description);
    }

    /** Text-note body (rich) with stored `<img data-path>` markers re-signed. */
    public function presentTextBody(): ?string
    {
        $body = $this->type === 'text' ? data_get($this->content, 'body') : null;

        return $body === null ? null : QuestionRules::hydrateStemMedia((string) $body);
    }

    /** Short-lived signed URL for file materials (private R2). */
    public function fileUrl(): ?string
    {
        if ($this->type !== 'file') {
            return null;
        }

        $path = data_get($this->content, 'path');

        return $path !== null ? s3Url($path) : null;
    }

    /** @return HasMany<CourseMaterialTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(CourseMaterialTarget::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
