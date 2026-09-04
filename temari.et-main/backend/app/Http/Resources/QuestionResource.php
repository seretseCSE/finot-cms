<?php

namespace App\Http\Resources;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * STAFF-ONLY shape — includes the answer key. Taker payloads never go
 * through this resource: the exam player is fed by
 * QuizAttemptService::paper(), which strips keys server-side.
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_bank_id' => $this->question_bank_id,
            'bank_name' => $this->whenLoaded('bank', fn () => $this->bank?->name),
            'parent_id' => $this->parent_id,
            'position' => $this->position,
            'children_count' => $this->whenCounted('children'),
            'type' => $this->type->value,
            'body' => $this->presentBody(),
            'answer_key' => $this->answer_key,
            'points' => (float) $this->points,
            'difficulty' => $this->difficulty,
            'topic' => $this->topic,
            'tags' => $this->tags ?? [],
            'source' => $this->source,
            'explanation' => $this->explanation,
            'status' => $this->status,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
