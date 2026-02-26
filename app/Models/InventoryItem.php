<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'item_code',
        'name',
        'category',
        'quantity',
        'unit',
        'purchase_date',
        'purchase_price',
        'supplier',
        'location',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    protected $dates = [
        'purchase_date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get formatted purchase date in Ethiopian
     */
    public function getEthiopianPurchaseDateAttribute(): string
    {
        if (!$this->purchase_date) {
            return '';
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->purchase_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->purchase_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->purchase_date)['year'];
    }

    /**
     * Get current stock (computed from movements)
     */
    public function getCurrentStockAttribute(): float
    {
        $stockIn = $this->movements()
            ->where('movement_type', 'Stock In')
            ->sum('quantity');

        $stockOut = $this->movements()
            ->where('movement_type', 'Stock Out')
            ->sum('quantity');

        return $this->quantity + $stockIn - $stockOut;
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Active' => 'green',
            'Damaged' => 'orange',
            'Lost' => 'red',
            'Disposed' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Check if item can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->movements()->exists();
    }

    /**
     * Mark as damaged
     */
    public function markAsDamaged(?string $notes = null): void
    {
        $this->update([
            'status' => 'Damaged',
            'notes' => $notes,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'inventory_item_marked_damaged',
            'entity_id' => $this->id,
            'entity_type' => 'inventory_item',
            'old_value' => json_encode(['status' => 'Active']),
            'new_value' => json_encode(['status' => 'Damaged', 'notes' => $notes]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Mark as lost
     */
    public function markAsLost(?string $notes = null): void
    {
        $this->update([
            'status' => 'Lost',
            'notes' => $notes,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'inventory_item_marked_lost',
            'entity_id' => $this->id,
            'entity_type' => 'inventory_item',
            'old_value' => json_encode(['status' => 'Active']),
            'new_value' => json_encode(['status' => 'Lost', 'notes' => $notes]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Mark as disposed
     */
    public function markAsDisposed(?string $notes = null): void
    {
        $this->update([
            'status' => 'Disposed',
            'notes' => $notes,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'inventory_item_marked_disposed',
            'entity_id' => $this->id,
            'entity_type' => 'inventory_item',
            'old_value' => json_encode(['status' => 'Active']),
            'new_value' => json_encode(['status' => 'Disposed', 'notes' => $notes]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'inventory_items';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Inventory Items';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-archive-box';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }
}
