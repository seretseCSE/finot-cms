<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\ChatPresenter;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything addressed at an EXISTING message — `chat/messages/{message}/*` in
 * the staff lane and `me/chat/messages/{message}/*` in the relationship lane.
 *
 * Split out of ChatController (ADR-019), which owns conversations, the
 * directory and uploads. The seam is the route parameter: these actions all
 * resolve a ChatMessage and re-derive authority from ITS conversation via
 * ConversationAccess, never from request headers — same rule as the rest of
 * the engine, so both lanes share one implementation.
 */
class ChatMessageController extends Controller
{
    public function __construct(
        private readonly ConversationAccess $access,
        private readonly ChatService $chat,
    ) {
    }

    public function update(Request $request, ChatMessage $message): JsonResponse
    {
        $user = $request->user();

        abort_unless((int) $message->user_id === $user->id, 403);
        abort_unless($message->editableWindowOpen(), 422, 'The edit window has closed.');
        abort_if($message->trashed() || $message->kind === 'system', 422);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        // Editing must not bypass the template gate the send obeyed.
        $this->access->assertTemplateCompliance($user, $message->conversation, $data['body']);

        $this->chat->edit($message, $data['body']);

        return response()->json(['data' => ChatPresenter::message($message->load(['author:id,name,avatar_path', 'reactions'])), 'message' => 'Message updated.']);
    }

    public function destroy(Request $request, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $conversation = $message->conversation;

        abort_unless(
            (int) $message->user_id === $user->id || $this->access->canModerate($user, $conversation),
            403,
        );

        $this->chat->remove($message);

        return response()->json(['message' => 'Message removed.']);
    }

    public function pin(Request $request, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $conversation = $message->conversation;

        abort_unless($this->access->canManagePins($user, $conversation), 403);
        abort_if(
            $message->trashed() || $message->kind === 'system' || $message->status !== ChatMessage::STATUS_SENT,
            422,
            'This message cannot be pinned.',
        );

        $this->chat->setPinned($user, $message, ! $message->isPinned());

        return response()->json([
            'data' => ChatPresenter::message(
                $message->fresh(['author:id,name,avatar_path', 'replyTo.author:id,name', 'reactions']),
            ),
            'message' => $message->isPinned() ? 'Pinned.' : 'Unpinned.',
        ]);
    }

    public function react(Request $request, ChatMessage $message): JsonResponse
    {
        $user = $request->user();

        abort_if($this->access->accessMode($user, $message->conversation) === null, 403);
        abort_if($message->trashed() || $message->status !== ChatMessage::STATUS_SENT, 422);

        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);

        $this->chat->toggleReaction($user, $message, $data['emoji']);

        return response()->json(['data' => ChatPresenter::message($message->load(['author:id,name,avatar_path', 'reactions']))]);
    }

    /*
    |--------------------------------------------------------------------------
    | Communication-book decisions
    |--------------------------------------------------------------------------
    */

    public function approve(Request $request, ChatMessage $message): JsonResponse
    {
        $this->assertPendingModeratable($request->user(), $message);

        $this->chat->approve($request->user(), $message);

        return response()->json(['message' => 'Message approved and delivered.']);
    }

    public function reject(Request $request, ChatMessage $message): JsonResponse
    {
        $this->assertPendingModeratable($request->user(), $message);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $this->chat->reject($request->user(), $message, $data['note'] ?? null);

        return response()->json(['message' => 'Message rejected.']);
    }

    private function assertPendingModeratable(User $user, ChatMessage $message): void
    {
        abort_unless($message->status === ChatMessage::STATUS_PENDING, 422, 'This message was already decided.');
        abort_unless($this->access->canModerate($user, $message->conversation), 403);
    }
}
