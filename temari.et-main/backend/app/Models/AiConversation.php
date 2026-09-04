<?php

namespace App\Models;

use App\Enums\AiLane;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One AI chat session (see the migration). Strictly self-scoped — every
 * query MUST go through scopeOwnedBy; there is no supervisory read over a
 * user's AI conversations, ever. The transcript itself lives in the Laravel
 * AI SDK tables keyed by `uuid`.
 */
#[Fillable([
    'uuid', 'user_id', 'lane', 'school_id', 'branch_id', 'student_id',
    'title', 'pinned_at', 'last_message_at',
])]
class AiConversation extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lane' => AiLane::class,
            'pinned_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
