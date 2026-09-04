<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Events\ChatMessageUpdated;
use App\Jobs\NotifyChatAudienceJob;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\Student;
use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * The chat write pipeline (ADR-019): create conversations, send through the
 * communication-book gate, decide pending messages, react/edit/remove, and
 * keep per-user state. Every visible mutation broadcasts on the conversation
 * channel AND flows through the Notifier so muted/offline users still get
 * their folded in-app rows. SMS never leaves here except the emergency lane
 * (chat.emergency via the platform whitelist).
 */
class ChatService
{
    public function __construct(
        private readonly ConversationAccess $access,
        private readonly Notifier $notifier,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Conversation creation
    |--------------------------------------------------------------------------
    */

    /**
     * Find-or-create the ONE direct thread for a pair: family threads key on
     * student × staff user (all the child's guardians share it), staff↔staff
     * threads on the sorted user pair.
     */
    public function direct(User $creator, int $schoolId, ?int $branchId, ?Student $student, User $staffSide): Conversation
    {
        $key = Conversation::directKeyFor($schoolId, $student?->id, $staffSide->id, $creator->id);

        return DB::transaction(function () use ($creator, $schoolId, $branchId, $student, $staffSide, $key): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                ['direct_key' => $key],
                [
                    'school_id' => $schoolId,
                    'branch_id' => $branchId,
                    'kind' => 'direct',
                    'student_id' => $student?->id,
                    'created_by' => $creator->id,
                ],
            );

            // Family threads persist only the STAFF side; guardians derive
            // from the live link. Staff↔staff threads persist both.
            $conversation->participants()->firstOrCreate(['user_id' => $staffSide->id], []);
            if ($student === null && $creator->id !== $staffSide->id) {
                $conversation->participants()->firstOrCreate(['user_id' => $creator->id], []);
            }

            return $conversation;
        });
    }

    /**
     * @param  list<int>  $userIds
     */
    public function createGroup(User $creator, int $schoolId, ?int $branchId, string $title, array $userIds): Conversation
    {
        return DB::transaction(function () use ($creator, $schoolId, $branchId, $title, $userIds): Conversation {
            $conversation = Conversation::query()->create([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'kind' => 'group',
                'title' => $title,
                'created_by' => $creator->id,
            ]);

            $conversation->participants()->create(['user_id' => $creator->id, 'role' => 'owner']);
            foreach (array_unique($userIds) as $userId) {
                if ((int) $userId !== $creator->id) {
                    $conversation->participants()->create(['user_id' => (int) $userId]);
                }
            }

            return $conversation;
        });
    }

    /**
     * @param  list<array{audience: string, branch_id?: ?int, grade_level_id?: ?int, section_id?: ?int, job_title?: ?string}>  $targets
     */
    public function createChannel(User $creator, int $schoolId, ?int $branchId, string $title, string $posting, array $targets): Conversation
    {
        return DB::transaction(function () use ($creator, $schoolId, $branchId, $title, $posting, $targets): Conversation {
            $conversation = Conversation::query()->create([
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'kind' => 'channel',
                'title' => $title,
                'created_by' => $creator->id,
                'settings' => ['posting' => $posting],
            ]);

            // The creator is always a MEMBER of their own channel — an explicit
            // participant row, independent of the audience rules. Without it a
            // director who targets only families matches no rule and would fall
            // to read-only supervisor (audit) access, unable to post their own
            // announcements.
            $conversation->participants()->create(['user_id' => $creator->id, 'role' => 'owner']);

            foreach ($targets as $target) {
                $conversation->targets()->create([
                    'audience' => $target['audience'],
                    'branch_id' => $target['branch_id'] ?? $branchId,
                    'grade_level_id' => $target['grade_level_id'] ?? null,
                    'section_id' => $target['section_id'] ?? null,
                    'job_title' => $target['job_title'] ?? null,
                ]);
            }

            return $conversation;
        });
    }

    /**
     * The reusable context-thread mount: any feature gets a chat by anchoring
     * a conversation to its domain object (assignment × student, transfer…).
     *
     * @param  list<int>  $participantUserIds
     */
    public function forContext(string $contextType, int $contextId, ?int $schoolId, ?int $branchId, array $participantUserIds, ?int $studentId = null): Conversation
    {
        // school NULL = a platform-level thread (tutoring marketplace) —
        // access is purely participant-based, no tenant scope involved.
        return DB::transaction(function () use ($contextType, $contextId, $schoolId, $branchId, $participantUserIds, $studentId): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                ['context_type' => $contextType, 'context_id' => $contextId, 'student_id' => $studentId],
                ['school_id' => $schoolId, 'branch_id' => $branchId, 'kind' => 'context'],
            );

            foreach (array_unique($participantUserIds) as $userId) {
                $conversation->participants()->firstOrCreate(['user_id' => (int) $userId], []);
            }

            return $conversation;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    */

    /**
     * Send through the communication-book gate. Returns the stored message —
     * status 'pending' means it is parked for a director and INVISIBLE to
     * everyone but the author and the approval inbox.
     *
     * @param  array{body?: ?string, kind?: string, attachments?: ?list<array<string, mixed>>, reply_to_id?: ?int, client_uuid?: ?string, emergency?: bool}  $payload
     */
    public function send(User $author, Conversation $conversation, array $payload): ChatMessage
    {
        // Idempotency: a 3G retry with the same client uuid re-serves the row.
        if (! empty($payload['client_uuid'])) {
            $existing = $conversation->messages()
                ->where('user_id', $author->id)
                ->where('client_uuid', $payload['client_uuid'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $pending = $this->access->requiresApproval($author, $conversation);
        $emergency = (bool) ($payload['emergency'] ?? false);

        $message = DB::transaction(function () use ($author, $conversation, $payload, $pending, $emergency): ChatMessage {
            $message = $conversation->messages()->create([
                'user_id' => $author->id,
                'kind' => $payload['kind'] ?? 'text',
                'body' => $payload['body'] ?? null,
                'attachments' => $payload['attachments'] ?? null,
                'meta' => $emergency ? ['emergency' => true] : null,
                'reply_to_id' => $payload['reply_to_id'] ?? null,
                'status' => $pending ? ChatMessage::STATUS_PENDING : ChatMessage::STATUS_SENT,
                'client_uuid' => $payload['client_uuid'] ?? null,
            ]);

            $this->indexMentions($message, $conversation);

            if (! $pending) {
                $conversation->forceFill(['last_message_at' => now()])->save();
            }

            return $message;
        });

        if ($pending) {
            $this->notifyModerators($message, $conversation);
        } else {
            ChatMessageSent::dispatch($message);
            $this->notifyRecipients($message, $conversation);
        }

        return $message;
    }

    /** Approve a pending communication-book message — NOW the family sees it. */
    public function approve(User $reviewer, ChatMessage $message): ChatMessage
    {
        $message->update([
            'status' => ChatMessage::STATUS_SENT,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
        $message->conversation->forceFill(['last_message_at' => now()])->save();

        ChatMessageSent::dispatch($message);
        $this->notifyRecipients($message, $message->conversation);

        $this->notifier->toUser($message->author, 'chat.message_decided', [
            'status' => 'approved',
        ], [
            'link' => "/messages?c={$message->conversation_id}",
            'schoolId' => (int) $message->conversation->school_id,
            'branchId' => $message->conversation->branch_id,
            'dedupeKey' => "chat-decided:{$message->conversation_id}",
        ]);

        return $message;
    }

    public function reject(User $reviewer, ChatMessage $message, ?string $note): ChatMessage
    {
        $message->update([
            'status' => ChatMessage::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->notifier->toUser($message->author, 'chat.message_decided', [
            'status' => 'rejected',
        ], [
            'link' => "/messages?c={$message->conversation_id}",
            'schoolId' => (int) $message->conversation->school_id,
            'branchId' => $message->conversation->branch_id,
            'dedupeKey' => "chat-decided:{$message->conversation_id}",
        ]);

        return $message;
    }

    /**
     * Forward existing messages into another conversation — each copied as a
     * NEW message carrying a `forwarded` meta marker (original author + source
     * title). Forwards ride the same communication-book gate as a normal post,
     * so a teacher forwarding into a family-facing thread still waits for a
     * director when the branch requires it.
     *
     * @param  iterable<int, ChatMessage>  $sources
     * @return list<ChatMessage>
     */
    public function forward(User $author, Conversation $target, iterable $sources): array
    {
        $created = [];

        foreach ($sources as $source) {
            $pending = $this->access->requiresApproval($author, $target);

            $message = DB::transaction(function () use ($author, $target, $source, $pending): ChatMessage {
                $message = $target->messages()->create([
                    'user_id' => $author->id,
                    'kind' => $source->kind === 'voice' ? 'voice' : 'text',
                    'body' => $source->body,
                    'attachments' => $source->attachments,
                    'meta' => ['forwarded' => [
                        'from' => $source->author?->name,
                        'origin' => $source->conversation?->title,
                    ]],
                    'status' => $pending ? ChatMessage::STATUS_PENDING : ChatMessage::STATUS_SENT,
                ]);

                if (! $pending) {
                    $target->forceFill(['last_message_at' => now()])->save();
                }

                return $message;
            });

            if ($pending) {
                $this->notifyModerators($message, $target);
            } else {
                ChatMessageSent::dispatch($message);
                $this->notifyRecipients($message, $target);
            }

            $created[] = $message;
        }

        return $created;
    }

    /*
    |--------------------------------------------------------------------------
    | Message mutations
    |--------------------------------------------------------------------------
    */

    /** Pin or unpin a message; a pin drops a system marker, an unpin is silent. */
    public function setPinned(User $user, ChatMessage $message, bool $pinned): ChatMessage
    {
        $message->update([
            'pinned_at' => $pinned ? now() : null,
            'pinned_by' => $pinned ? $user->id : null,
        ]);

        ChatMessageUpdated::dispatch($message);

        if ($pinned) {
            $this->systemMessage($message->conversation, 'pinned', ['name' => $user->name]);
        }

        return $message;
    }

    public function edit(ChatMessage $message, string $body): ChatMessage
    {
        $message->update(['body' => $body, 'edited_at' => now()]);

        if ($message->status === ChatMessage::STATUS_SENT) {
            ChatMessageUpdated::dispatch($message);
        }

        return $message;
    }

    public function remove(ChatMessage $message): void
    {
        $message->delete();

        ChatMessageUpdated::dispatch($message);
    }

    public function toggleReaction(User $user, ChatMessage $message, string $emoji): void
    {
        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        $existing !== null
            ? $existing->delete()
            : $message->reactions()->create(['user_id' => $user->id, 'emoji' => $emoji]);

        ChatMessageUpdated::dispatch($message->refresh());
    }

    /** A localized inline marker row ("X joined", "approved by Y"). */
    public function systemMessage(Conversation $conversation, string $event, array $params = []): ChatMessage
    {
        $message = $conversation->messages()->create([
            'kind' => 'system',
            'meta' => ['event' => $event, 'params' => $params],
        ]);

        ChatMessageSent::dispatch($message);

        return $message;
    }

    /*
    |--------------------------------------------------------------------------
    | Per-user state
    |--------------------------------------------------------------------------
    */

    public function state(User $user, Conversation $conversation): ConversationUserState
    {
        return ConversationUserState::query()->firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            [],
        );
    }

    public function markRead(User $user, Conversation $conversation, int $messageId): void
    {
        $state = $this->state($user, $conversation);

        if ($state->last_read_message_id === null || $messageId > (int) $state->last_read_message_id) {
            $state->update(['last_read_message_id' => $messageId]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    private function notifyRecipients(ChatMessage $message, Conversation $conversation): void
    {
        // Mentions first — they pierce the conversation mute.
        $mentionedIds = $message->mentions()->pluck('user_id')->map(fn ($id): int => (int) $id)->all();

        if ($mentionedIds !== []) {
            $this->notifier->toUsers(
                User::query()->whereIn('id', $mentionedIds)->where('status', 'active')->get(),
                'chat.mention',
                ['sender' => $message->author?->name ?? '', 'preview' => str($message->body ?? '')->limit(80)->toString()],
                $this->notifyOptions($message, $conversation, "chat-mention:{$conversation->id}"),
            );
        }

        // Channels resolve their (possibly huge) audience off-request.
        if ($conversation->kind === 'channel') {
            NotifyChatAudienceJob::dispatch($message->id, $mentionedIds);

            return;
        }

        $muted = ConversationUserState::query()
            ->where('conversation_id', $conversation->id)
            ->where('muted_until', '>', now())
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $recipients = $this->access->audienceUsers($conversation)
            ->reject(fn (User $u): bool => in_array($u->id, $muted, true) || in_array($u->id, $mentionedIds, true));

        $this->notifier->toUsers(
            $recipients,
            'chat.message',
            ['sender' => $message->author?->name ?? '', 'preview' => str($message->body ?? '')->limit(80)->toString()],
            $this->notifyOptions($message, $conversation, "chat:{$conversation->id}"),
        );
    }

    private function notifyModerators(ChatMessage $message, Conversation $conversation): void
    {
        $this->notifier->toStaff(
            (int) $conversation->school_id,
            $conversation->branch_id === null ? null : (int) $conversation->branch_id,
            'chat.moderate',
            'chat.approval_pending',
            ['sender' => $message->author?->name ?? ''],
            [
                'link' => '/messages/approvals',
                'dedupeKey' => "chat-approval:{$conversation->school_id}:{$conversation->branch_id}",
                'exceptUserId' => $message->user_id,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function notifyOptions(ChatMessage $message, Conversation $conversation, string $dedupeKey): array
    {
        return [
            'link' => "/messages?c={$conversation->id}",
            'schoolId' => (int) $conversation->school_id,
            'branchId' => $conversation->branch_id === null ? null : (int) $conversation->branch_id,
            'dedupeKey' => $dedupeKey,
            'exceptUserId' => $message->user_id,
        ];
    }

    private function indexMentions(ChatMessage $message, Conversation $conversation): void
    {
        $ids = $message->mentionedUserIds();

        if ($ids === []) {
            return;
        }

        // Only actual members can be mentioned — forged tokens are ignored.
        // Channels skip the resolve (their audience can be huge); a stray
        // channel mention only ever notifies someone already gated by prefs.
        $memberIds = $conversation->kind === 'channel'
            ? null
            : $this->access->audienceUsers($conversation)->pluck('id')->all();

        foreach ($ids as $userId) {
            if ($memberIds === null || in_array($userId, $memberIds, true)) {
                $message->mentions()->create(['user_id' => $userId]);
            }
        }
    }
}
