<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Ai\Models\ConversationMessage;

/**
 * 👍/👎 on assistant messages. One verdict per (user, message), switchable.
 */
class AiFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'message_id' => ['required', 'string', 'max:36'],
            'rating' => ['required', Rule::in(['up', 'down'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = ConversationMessage::query()
            ->where('id', $data['message_id'])
            ->where('role', 'assistant')
            ->first();

        abort_if($message === null, 404, 'Message not found.');

        $conversation = AiConversation::query()
            ->ownedBy($user)
            ->where('uuid', $message->conversation_id)
            ->first();

        abort_if($conversation === null, 404, 'Message not found.');

        AiFeedback::query()->updateOrCreate(
            ['user_id' => $user->id, 'message_id' => $message->id],
            [
                'ai_conversation_id' => $conversation->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ],
        );

        return response()->json(['message' => 'Thanks for the feedback.']);
    }
}
