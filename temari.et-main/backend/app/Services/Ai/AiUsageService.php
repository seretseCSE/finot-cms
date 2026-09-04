<?php

namespace App\Services\Ai;

use App\Models\AiUsageLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of ai_usage_ledgers. Message counts increment when a
 * prompt is ACCEPTED (before streaming — abandoned streams still consumed a
 * model call); token counts land when the response completes.
 */
class AiUsageService
{
    public function messagesUsedToday(User $user): int
    {
        return (int) AiUsageLedger::query()
            ->where('user_id', $user->id)
            ->where('date', today()->toDateString())
            ->value('messages');
    }

    public function recordMessage(User $user): void
    {
        $this->upsertToday($user, 1, 0, 0);
    }

    public function recordTokens(User $user, int $inputTokens, int $outputTokens): void
    {
        $this->upsertToday($user, 0, max(0, $inputTokens), max(0, $outputTokens));
    }

    /** Atomic insert-or-increment of today's row. */
    private function upsertToday(User $user, int $messages, int $inputTokens, int $outputTokens): void
    {
        AiUsageLedger::query()->upsert(
            [[
                'user_id' => $user->id,
                'date' => today()->toDateString(),
                'messages' => $messages,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id', 'date'],
            [
                'messages' => DB::raw('ai_usage_ledgers.messages + '.$messages),
                'input_tokens' => DB::raw('ai_usage_ledgers.input_tokens + '.$inputTokens),
                'output_tokens' => DB::raw('ai_usage_ledgers.output_tokens + '.$outputTokens),
                'updated_at' => now(),
            ],
        );
    }
}
