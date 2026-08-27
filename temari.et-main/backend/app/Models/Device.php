<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'school_id', 'branch_id', 'name', 'location', 'serial_no', 'token_hash',
    'audience', 'is_active', 'last_seen_at', 'last_event_at', 'last_roster_at',
])]
class Device extends Model
{
    use SoftDeletes;

    public const AUDIENCES = ['students', 'employees', 'both'];

    /** A device is "online" when it has phoned home this recently. */
    public const ONLINE_WINDOW_MINUTES = 10;

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_event_at' => 'datetime',
            'last_roster_at' => 'datetime',
        ];
    }

    /** Mint a fresh plaintext token; the caller stores the hash and shows the token ONCE. */
    public static function mintToken(): string
    {
        return 'tmd_'.Str::random(40);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<DeviceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class);
    }
}
