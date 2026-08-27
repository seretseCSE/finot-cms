<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'card_uid', 'holder_type', 'holder_id', 'status',
    'replaced_by_id', 'issued_on', 'note', 'deactivated_at', 'issued_by',
])]
class IdCard extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'lost', 'revoked', 'replaced'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<IdCard, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Deactivate this card into $status (lost|revoked|replaced). */
    public function deactivate(string $status, ?int $replacedById = null): void
    {
        $this->update([
            'status' => $status,
            'replaced_by_id' => $replacedById,
            'deactivated_at' => now(),
        ]);
    }
}
