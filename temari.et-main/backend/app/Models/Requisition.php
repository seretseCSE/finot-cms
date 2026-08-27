<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff request to the branch store (the Model-22 workflow):
 * pending → approved/declined (countersigned, never one's own) → issued.
 */
#[Fillable([
    'school_id', 'branch_id', 'status', 'requested_by', 'purpose',
    'decided_by', 'decided_at', 'decline_reason', 'fulfilled_at',
])]
class Requisition extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'decided_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
