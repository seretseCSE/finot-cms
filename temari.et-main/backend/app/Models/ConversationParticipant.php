<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit membership for direct / group / context conversations. Channels
 * never have rows here — their audience derives from conversation_targets.
 */
#[Fillable(['conversation_id', 'user_id', 'role', 'left_at'])]
class ConversationParticipant extends Model
{
    protected function casts(): array
    {
        return [
            'left_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
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
