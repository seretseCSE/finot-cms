<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device (user-agent fingerprint) a user has signed in from. First-seen
 * fingerprints fire `security.new_device` — see AuthController@login.
 */
#[Fillable(['user_id', 'fingerprint', 'label', 'ip', 'last_seen_at'])]
class UserDevice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
