<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One chat message (ADR-019). Official record: soft-delete renders as
 * "removed", edits stamp edited_at, and the communication-book approval gate
 * parks teacher→parent messages as 'pending' until a director decides.
 */
#[Fillable([
    'conversation_id', 'user_id', 'kind', 'body', 'attachments', 'meta',
    'reply_to_id', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    'client_uuid', 'edited_at', 'pinned_at', 'pinned_by',
])]
class ChatMessage extends Model
{
    use SoftDeletes;

    public const KINDS = ['text', 'voice', 'system'];

    public const STATUS_SENT = 'sent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    /** Minutes after sending during which the author may still edit. */
    public const EDIT_WINDOW_MINUTES = 15;

    protected $attributes = [
        'kind' => 'text',
        'status' => self::STATUS_SENT,
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'meta' => 'array',
            'reviewed_at' => 'datetime',
            'edited_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isEmergency(): bool
    {
        return (bool) ($this->meta['emergency'] ?? false);
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    public function editableWindowOpen(): bool
    {
        return $this->created_at !== null
            && $this->created_at->diffInMinutes(now()) < self::EDIT_WINDOW_MINUTES;
    }

    /**
     * User ids mentioned inline as @[user:123] tokens.
     *
     * @return list<int>
     */
    public function mentionedUserIds(): array
    {
        if ($this->body === null) {
            return [];
        }

        preg_match_all('/@\[user:(\d+)\]/', $this->body, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    /** @return HasMany<ChatMessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class);
    }

    /** @return HasMany<ChatMessageMention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(ChatMessageMention::class);
    }
}
