<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
use App\Services\Notify\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Channel fan-out: resolving a channel's audience can touch thousands of
 * users (a school-wide announcement), so it happens here, off-request. The
 * Notifier itself chunks big audiences further. Emergency messages ride the
 * chat.emergency event (critical severity — pierces category mutes, SMS via
 * the platform whitelist); ordinary posts fold under one dedupe key per
 * conversation and respect per-conversation mutes.
 */
class NotifyChatAudienceJob implements ShouldQueue
{
    use Queueable;

    /** @param  list<int>  $alreadyNotifiedUserIds */
    public function __construct(
        public int $messageId,
        public array $alreadyNotifiedUserIds = [],
    ) {
    }

    public function handle(ConversationAccess $access, Notifier $notifier): void
    {
        $message = ChatMessage::query()->with(['conversation', 'author:id,name'])->find($this->messageId);

        if ($message === null || $message->status !== ChatMessage::STATUS_SENT || $message->conversation === null) {
            return;
        }

        $conversation = $message->conversation;
        $emergency = $message->isEmergency();

        $muted = $emergency ? [] : ConversationUserState::query()
            ->where('conversation_id', $conversation->id)
            ->where('muted_until', '>', now())
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $recipients = $access->audienceUsers($conversation)
            ->reject(fn (User $u): bool => in_array($u->id, $muted, true)
                || in_array($u->id, $this->alreadyNotifiedUserIds, true));

        $options = [
            'link' => "/messages?c={$conversation->id}",
            'schoolId' => (int) $conversation->school_id,
            'branchId' => $conversation->branch_id === null ? null : (int) $conversation->branch_id,
            'dedupeKey' => $emergency ? null : "chat:{$conversation->id}",
            'exceptUserId' => $message->user_id,
        ];

        // The SMS line renders PLAIN text (never HTML) and, when the post
        // carries media SMS can't inline, a tap-through link to the thread. We
        // OVERRIDE the shared `preview` var for the SMS leg only (smsVars) — the
        // template itself stays `:preview`, so even a stale worker can never
        // leak a raw placeholder, and the in-app feed keeps the clean preview.
        if ($emergency) {
            $options['smsVars'] = ['preview' => $this->smsPreview($message, $conversation)];
        }

        $notifier->toUsers(
            $recipients,
            $emergency ? 'chat.emergency' : 'chat.message',
            [
                'sender' => $message->author?->name ?? '',
                'channel' => $conversation->title ?? '',
                'preview' => str(strip_tags($message->body ?? ''))->limit(80)->toString(),
            ],
            $options,
        );
    }

    /** Plain-text SMS body: the message stripped of any markup, plus a link to the media when present. */
    private function smsPreview(ChatMessage $message, Conversation $conversation): string
    {
        $text = str(strip_tags($message->body ?? ''))->limit(80)->toString();

        if (empty($message->attachments)) {
            return $text;
        }

        $base = rtrim((string) config('sms.frontend_url'), '/');

        return $base === '' ? $text : trim("{$text} {$base}/messages?c={$conversation->id}");
    }
}
