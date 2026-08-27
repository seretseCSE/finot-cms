<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ConversationAccess;

/**
 * Thin delegate to the chat access kernel (ADR-019) — ConversationAccess is
 * the single source of truth; this class only adapts it to Laravel's
 * authorize() surface.
 */
class ConversationPolicy
{
    public function __construct(private readonly ConversationAccess $access) {}

    /** Member OR read-only audit (chat.moderate at scope). */
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->access->accessMode($user, $conversation) !== null;
    }

    public function post(User $user, Conversation $conversation): bool
    {
        return $this->access->canPost($user, $conversation);
    }

    public function moderate(User $user, Conversation $conversation): bool
    {
        return $this->access->canModerate($user, $conversation);
    }
}
