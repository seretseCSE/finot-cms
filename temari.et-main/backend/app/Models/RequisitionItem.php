<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One requested line. quantity_issued accrues as the store fulfils; the
 * matching ledger movement is the source of truth.
 */
#[Fillable([
    'requisition_id', 'inventory_item_id', 'quantity_requested',
    'quantity_approved', 'quantity_issued',
])]
class RequisitionItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:2',
            'quantity_approved' => 'decimal:2',
            'quantity_issued' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
