<?php

namespace App\Models;

use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One item inside a module: video / reading / file / quiz (the quiz engine
 * is reused — never a second player).
 */
#[Fillable([
    'course_id', 'course_module_id', 'type', 'title', 'content', 'quiz_id',
    'duration_minutes', 'is_preview', 'sort_order',
])]
class CourseLesson extends Model
{
    use SoftDeletes;

    public const TYPES = ['video', 'reading', 'file', 'quiz'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'duration_minutes' => 'integer',
            'is_preview' => 'boolean',
        ];
    }

    /** Reading body (rich) with stored `<img data-path>` markers re-signed. */
    public function presentBody(): ?string
    {
        $body = $this->type === 'reading' ? data_get($this->content, 'body') : null;

        return $body === null ? null : QuestionRules::hydrateStemMedia((string) $body);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
