<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One in-app feed row. Stores the catalog event key + params — NEVER rendered
 * text (title/body localize at read time via lang/notifications.php, so the
 * feed follows the reader's language). No soft deletes on purpose: rows are
 * delivery state, pruned after 90 days; history lives in the domain tables.
 */
#[Fillable(['user_id', 'event', 'category', 'school_id', 'branch_id', 'data', 'link', 'dedupe_key', 'read_at'])]
class Notification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
