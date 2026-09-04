<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached quantity per branch × item. quantity_on_hand is OUTSIDE fillable on
 * purpose — only StockLedger may move stock (it forceFills under a row lock).
 */
#[Fillable(['school_id', 'branch_id', 'inventory_item_id'])]
class StockLevel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity_on_hand' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
