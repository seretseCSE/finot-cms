<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'item_id',
        'movement_type',
        'sub_type',
        'quantity',
        'movement_date',
        'recipient_source',
        'reference_number',
        'notes',
        'override_justification',
        'recorded_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'movement_date' => 'date',
    ];

    protected $dates = [
        'movement_date',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get formatted movement date in Ethiopian
     */
    public function getEthiopianMovementDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->movement_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->movement_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->movement_date)['year'];
    }

    /**
     * Get movement type color
     */
    public function getMovementTypeColorAttribute(): string
    {
        return match($this->movement_type) {
            'Stock In' => 'green',
            'Stock Out' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'inventory_movements';
    }
}
