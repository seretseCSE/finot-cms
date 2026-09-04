<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Services\Chat\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message became visible in a conversation — on send, or when a pending
 * (communication-book) message is approved. Broadcast on the conversation's
 * private channel; auth in routes/channels.php goes through
 * ConversationAccess, so only members receive it.
 */
class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['message' => ChatPresenter::message($this->message->loadMissing(['author:id,name,avatar_path', 'replyTo.author:id,name', 'reactions']))];
    }
}
