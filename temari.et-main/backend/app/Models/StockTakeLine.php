<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One counted item. counted_quantity NULL = not counted yet — posting skips
 * it rather than guessing zero.
 */
#[Fillable([
    'stock_take_id', 'inventory_item_id', 'expected_quantity', 'counted_quantity',
])]
class StockTakeLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockTake, $this>
     */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
