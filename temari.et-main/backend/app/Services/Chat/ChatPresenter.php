<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\Conversation;

/**
 * The single message/conversation → API array mapping — controllers and
 * broadcast events must ship the exact same shape so a socket-delivered
 * message is indistinguishable from a fetched one.
 */
class ChatPresenter
{
    /** @return array<string, mixed> */
    public static function message(ChatMessage $message): array
    {
        $removed = $message->trashed();

        $reactions = [];
        foreach ($message->reactions as $reaction) {
            $reactions[$reaction->emoji]['count'] = ($reactions[$reaction->emoji]['count'] ?? 0) + 1;
            $reactions[$reaction->emoji]['user_ids'][] = (int) $reaction->user_id;
        }

        return [
            'id' => $message->id,
            'conversation_id' => (int) $message->conversation_id,
            'kind' => $message->kind,
            'body' => $removed ? null : $message->body,
            'attachments' => $removed ? [] : collect($message->attachments ?? [])->map(fn (array $file): array => [
                'name' => $file['name'] ?? 'file',
                'size' => $file['size'] ?? null,
                'mime_type' => $file['mime_type'] ?? null,
                'duration' => $file['duration'] ?? null,
                'url' => isset($file['path']) ? s3Url($file['path']) : null,
            ])->values()->all(),
            'meta' => $message->kind === 'system' ? $message->meta : [
                'emergency' => $message->isEmergency(),
                'forwarded' => $message->meta['forwarded'] ?? null,
            ],
            'pinned' => $message->pinned_at !== null,
            'status' => $message->status,
            'review_note' => $message->review_note,
            'author' => $message->author === null ? null : [
                'id' => $message->author->id,
                'name' => $message->author->name,
                'avatar_url' => $message->author->avatarUrl(),
            ],
            'reply_to' => $message->replyTo === null || $message->replyTo->trashed() ? null : [
                'id' => $message->replyTo->id,
                'body' => str($message->replyTo->body ?? '')->limit(120)->toString(),
                'author_name' => $message->replyTo->author?->name,
                'kind' => $message->replyTo->kind,
            ],
            'reactions' => collect($reactions)->map(fn (array $r, string $emoji): array => [
                'emoji' => $emoji,
                'count' => $r['count'],
                'user_ids' => $r['user_ids'],
            ])->values()->all(),
            'removed' => $removed,
            'edited_at' => $message->edited_at,
            'client_uuid' => $message->client_uuid,
            'created_at' => $message->created_at,
        ];
    }

    /**
     * Conversation list row. $extra carries per-user computed bits (unread,
     * state, display name/avatar) the caller resolves in bulk.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function conversation(Conversation $conversation, array $extra = []): array
    {
        return [
            'id' => $conversation->id,
            'kind' => $conversation->kind,
            'title' => $conversation->title,
            'system' => $conversation->setting('system'),
            'posting' => $conversation->setting('posting', 'all'),
            'school_id' => (int) $conversation->school_id,
            'branch_id' => $conversation->branch_id === null ? null : (int) $conversation->branch_id,
            'branch_name' => $conversation->branch?->name,
            'section_id' => $conversation->section_id === null ? null : (int) $conversation->section_id,
            'student' => $conversation->student === null ? null : [
                'id' => $conversation->student->id,
                'name' => $conversation->student->full_name,
            ],
            'context_type' => $conversation->context_type,
            'context_id' => $conversation->context_id,
            'archived' => $conversation->isArchived(),
            'last_message_at' => $conversation->last_message_at,
            'created_at' => $conversation->created_at,
            ...$extra,
        ];
    }
}
