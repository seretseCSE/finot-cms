<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One school → Temari.et card fulfilment request (see the migration for the
 * lifecycle). `delivered` additionally requires a linked replacement card.
 */
#[Fillable([
    'school_id', 'branch_id', 'id_card_id', 'holder_type', 'holder_id',
    'reason', 'note', 'status', 'requested_by', 'new_card_id',
])]
class CardRequest extends Model
{
    public const REASONS = ['lost', 'damaged', 'new'];

    public const STATUSES = ['requested', 'accepted', 'preparing', 'delivering', 'delivered', 'rejected'];

    /** Statuses still in flight — a holder may only have one open request. */
    public const OPEN_STATUSES = ['requested', 'accepted', 'preparing', 'delivering'];

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
    public function card(): BelongsTo
    {
        return $this->belongsTo(IdCard::class, 'id_card_id');
    }

    /**
     * @return BelongsTo<IdCard, $this>
     */
    public function newCard(): BelongsTo
    {
        return $this->belongsTo(IdCard::class, 'new_card_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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
}
