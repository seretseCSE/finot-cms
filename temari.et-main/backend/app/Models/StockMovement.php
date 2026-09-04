<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger row — the digital bin card line. Append-only: never updated,
 * never deleted; corrections are new adjustment movements. Created only by
 * App\Services\Inventory\StockLedger.
 */
#[Fillable([
    'school_id', 'branch_id', 'inventory_item_id', 'type', 'quantity',
    'quantity_change', 'quantity_after', 'unit_cost', 'requisition_id',
    'purchase_order_id', 'stock_take_id', 'expense_id', 'supplier_name',
    'recipient', 'reference', 'note', 'created_by',
])]
class StockMovement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:2',
            'quantity_change' => 'decimal:2',
            'quantity_after' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
