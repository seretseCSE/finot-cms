<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The item master (school-owned). Quantities never live here — stock_levels
 * caches per-branch quantity, stock_movements is the ledger.
 */
#[Fillable([
    'school_id', 'inventory_category_id', 'name', 'code', 'unit', 'is_asset',
    'reorder_level', 'description', 'is_active',
])]
class InventoryItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_asset' => 'boolean',
            'is_active' => 'boolean',
            'reorder_level' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
