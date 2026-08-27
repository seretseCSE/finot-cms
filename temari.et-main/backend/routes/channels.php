<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channel authorization
|--------------------------------------------------------------------------
|
| Private websocket channels (Reverb). Authorization mirrors the HTTP lane
| exactly: the chat conversation channel goes through ConversationAccess —
| the single chat access kernel (ADR-019) — so a socket can never leak a
| conversation its subscriber couldn't fetch over HTTP. Auth endpoint:
| POST /api/broadcasting/auth (Sanctum bearer token).
|
*/

Broadcast::channel('chat.conversation.{conversationId}', function (User $user, int $conversationId): bool {
    $conversation = Conversation::query()->find($conversationId);

    return $conversation !== null
        && app(ConversationAccess::class)->accessMode($user, $conversation) !== null;
});

// Per-user lane for conversation-list refreshes (badges, new threads).
Broadcast::channel('chat.user.{userId}', fn (User $user, int $userId): bool => $user->id === $userId);
