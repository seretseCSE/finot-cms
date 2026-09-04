<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OPTIONAL procurement lane — direct receiving never needs a PO. total_cost
 * is a cached sum of the lines, refreshed whenever lines change.
 */
#[Fillable([
    'school_id', 'branch_id', 'supplier_name', 'supplier_phone', 'status',
    'expected_on', 'note', 'total_cost', 'ordered_by', 'decided_by',
    'decided_at', 'decline_reason',
])]
class PurchaseOrder extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'expected_on' => 'date',
            'total_cost' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
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

    public function refreshTotalCost(): void
    {
        $total = (float) $this->items()
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) AS total')
            ->value('total');

        $this->forceFill(['total_cost' => round($total, 2)])->save();
    }
}
