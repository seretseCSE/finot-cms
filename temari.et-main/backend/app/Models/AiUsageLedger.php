<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user, per-day AI consumption row (see the migration). Written only by
 * AiUsageService::record — atomic upsert-increment, never direct writes.
 */
#[Fillable(['user_id', 'date', 'messages', 'input_tokens', 'output_tokens'])]
class AiUsageLedger extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
