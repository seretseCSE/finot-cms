<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform catalog row: an Ethiopian bank or mobile wallet (seeded by
 * BankSeeder). `logo` is a public path under /images/banks or null (the UI
 * falls back to initials).
 */
#[Fillable(['code', 'name', 'type', 'logo', 'is_active'])]
class Bank extends Model
{
    public const TYPE_BANK = 'bank';

    public const TYPE_WALLET = 'wallet';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
