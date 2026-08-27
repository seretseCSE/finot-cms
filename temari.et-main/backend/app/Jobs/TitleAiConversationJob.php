<?php

namespace App\Jobs;

use App\Models\AiConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;

/**
 * Name a fresh AI chat from its first exchange with the cheap model
 * (config temari-ai.light_model). Best-effort: any failure falls back to a
 * truncated first prompt so a session is never left called "New chat".
 */
class TitleAiConversationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $conversationId) {}

    public function handle(): void
    {
        $conversation = AiConversation::query()->find($this->conversationId);

        if ($conversation === null || $conversation->title !== 'New chat') {
            return;
        }

        $firstMessage = ConversationMessage::query()
            ->where('conversation_id', $conversation->uuid)
            ->where('role', 'user')
            ->orderBy('created_at')
            ->value('content');

        if ($firstMessage === null || trim((string) $firstMessage) === '') {
            return;
        }

        $fallback = Str::limit(trim((string) $firstMessage), 60, '…');

        try {
            $response = \Laravel\Ai\agent('You name chat conversations. Reply with ONLY the title.')
                ->prompt(
                    "Write a 3–6 word title (no quotes, no trailing punctuation, same language as the message) for a chat that starts with:\n\n"
                    .Str::limit((string) $firstMessage, 500),
                    model: (string) config('temari-ai.light_model'),
                    timeout: 30,
                );

            $title = trim(Str::limit(trim($response->text, " \t\n\r\"'."), 80, ''));
        } catch (\Throwable $e) {
            Log::info('AI title generation fell back to prompt excerpt.', ['error' => $e->getMessage()]);
            $title = $fallback;
        }

        $conversation->forceFill(['title' => $title !== '' ? $title : $fallback])->save();
    }
}
