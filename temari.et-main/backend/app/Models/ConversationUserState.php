<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lazily-created per-user conversation state: the read pointer (unread =
 * message ids above it), mute-until, and pin. One row regardless of whether
 * membership is explicit (participants) or rule-derived (targets).
 */
#[Fillable(['conversation_id', 'user_id', 'last_read_message_id', 'muted_until', 'pinned_at'])]
class ConversationUserState extends Model
{
    protected function casts(): array
    {
        return [
            'muted_until' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
