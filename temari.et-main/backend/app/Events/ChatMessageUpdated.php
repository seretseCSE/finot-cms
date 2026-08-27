<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Services\Chat\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** An existing message changed — edit, removal, or a reaction toggle. */
class ChatMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['message' => ChatPresenter::message($this->message->loadMissing(['author:id,name,avatar_path', 'replyTo.author:id,name', 'reactions']))];
    }
}
