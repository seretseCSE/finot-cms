<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\AiAgentFactory;
use App\Http\Controllers\Controller;
use App\Jobs\TitleAiConversationJob;
use App\Models\AiConversation;
use App\Services\Ai\AiEntitlementService;
use App\Services\Ai\AiUsageService;
use App\Services\Ai\ChatAttachments;
use Illuminate\Http\Request;
use Laravel\Ai\Files\File;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The chat wire: one prompt in → one streamed answer out (SSE, Vercel AI
 * data protocol). Entitlement/quota are enforced HERE on every prompt —
 * the daily message is spent when the prompt is accepted; token counts and
 * housekeeping (last_message_at, auto-title) land when the stream ends.
 *
 * Attachments (photos, PDFs, Word/Excel/PowerPoint, text files) go through
 * ChatAttachments — office files reach the model as extracted text.
 */
class AiChatController extends Controller
{
    public function send(
        Request $request,
        AiConversation $conversation,
        AiAgentFactory $agents,
        AiEntitlementService $entitlements,
        AiUsageService $usage,
        ChatAttachments $chatAttachments,
    ): Response {
        $user = $request->user();

        abort_unless($conversation->user_id === $user->id, 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:'.(int) config('temari-ai.max_prompt_length')],
            'attachments' => ['nullable', 'array', 'max:'.(int) config('temari-ai.max_attachments')],
            'attachments.*' => ChatAttachments::rules(),
        ]);

        $entitlements->assertCanPrompt($user, $conversation->lane, $conversation->school);

        $usage->recordMessage($user);

        $attachments = collect($request->file('attachments', []))
            ->map(fn ($file) => $chatAttachments->wrap($file))
            ->all();

        return $this->streamReply($request, $conversation, $agents, $usage, $data['content'], $attachments);
    }

    /**
     * Re-answer the last prompt: the trailing exchange (the last user turn
     * and everything after it) is removed, then the same content and
     * attachments run through the normal pipeline — so the transcript ends
     * up with ONE fresh exchange, never a duplicate. Costs a daily message
     * like any prompt.
     */
    public function regenerate(
        Request $request,
        AiConversation $conversation,
        AiAgentFactory $agents,
        AiEntitlementService $entitlements,
        AiUsageService $usage,
    ): Response {
        $user = $request->user();

        abort_unless($conversation->user_id === $user->id, 404);

        // Transcript order (created_at, then id) — same as the messages endpoint.
        $rows = ConversationMessage::query()
            ->where('conversation_id', $conversation->uuid)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $lastUserIndex = null;
        foreach ($rows as $index => $row) {
            if ($row->role === 'user') {
                $lastUserIndex = $index;
            }
        }

        abort_if($lastUserIndex === null, 422, 'There is nothing to regenerate yet.');

        $entitlements->assertCanPrompt($user, $conversation->lane, $conversation->school);

        /** @var ConversationMessage $lastUser */
        $lastUser = $rows[$lastUserIndex];
        $content = (string) $lastUser->content;

        $attachments = collect($lastUser->attachments ?? [])
            ->map(fn (mixed $stored) => is_array($stored) ? File::fromArray($stored) : null)
            ->filter()
            ->values()
            ->all();

        // Drop the tail — the pipeline re-stores the user turn with its attachments.
        ConversationMessage::query()
            ->whereIn('id', $rows->slice($lastUserIndex)->pluck('id'))
            ->delete();

        $usage->recordMessage($user);

        return $this->streamReply($request, $conversation, $agents, $usage, $content, $attachments);
    }

    /**
     * @param  array<int, File>  $attachments
     */
    private function streamReply(
        Request $request,
        AiConversation $conversation,
        AiAgentFactory $agents,
        AiUsageService $usage,
        string $content,
        array $attachments,
    ): Response {
        $user = $request->user();

        $agent = $agents->forConversation($conversation, $user);

        $response = $agent
            ->continue($conversation->uuid, $user)
            ->stream(
                $content,
                attachments: $attachments,
                model: ChatAttachments::modelFor($attachments),
                timeout: (int) config('temari-ai.timeout'),
            )
            ->usingVercelDataProtocol();

        $response->then(function (StreamedAgentResponse $streamed) use ($conversation, $usage, $user): void {
            if ($streamed->usage !== null) {
                $usage->recordTokens($user, $streamed->usage->promptTokens, $streamed->usage->completionTokens);
            }

            $conversation->forceFill(['last_message_at' => now()])->save();

            if ($conversation->title === 'New chat') {
                TitleAiConversationJob::dispatch($conversation->id);
            }
        });

        return $response->toResponse($request);
    }
}
