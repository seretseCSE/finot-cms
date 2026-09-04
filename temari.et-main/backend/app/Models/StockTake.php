<?php

namespace App\Models;

use App\Enums\StockTakeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One counting session. Counting never edits stock; POSTING writes the
 * differences to the ledger as adjustment movements.
 */
#[Fillable([
    'school_id', 'branch_id', 'inventory_category_id', 'status', 'note',
    'started_by', 'posted_by', 'posted_at',
])]
class StockTake extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockTakeStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockTakeLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTakeLine::class);
    }

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
