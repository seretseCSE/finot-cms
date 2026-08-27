<?php

namespace App\Http\Resources;

use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Monitor/grading shape for staff. Taker-facing attempt state is emitted by
 * the /me lane with results gated by the quiz's reveal policy.
 *
 * @mixin QuizAttempt
 */
class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'user_id' => $this->user_id,
            'student_id' => $this->student_id,
            'taker_name' => $this->whenLoaded('student', fn () => $this->student?->full_name)
                ?? $this->whenLoaded('user', fn () => $this->user?->name),
            'student_public_id' => $this->whenLoaded('student', fn () => $this->student?->public_id),
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'started_at' => $this->started_at,
            'deadline_at' => $this->deadline_at,
            'submitted_at' => $this->submitted_at,
            'graded_at' => $this->graded_at,
            'score' => $this->score !== null ? (float) $this->score : null,
            'max_score' => (float) $this->max_score,
            'pending_manual' => $this->pending_manual,
            'flag_count' => $this->flag_count,
            'integrity_log' => $this->when($request->routeIs('*attempt*') || $request->boolean('with_log'), $this->integrity_log ?? []),
        ];
    }
}
