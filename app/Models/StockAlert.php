<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    protected $fillable = [
        'item_id',
        'threshold',
        'current_stock',
        'status',
        'notes',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Active' => 'danger',
            'Acknowledged' => 'warning',
            'Resolved' => 'success',
            default => 'gray',
        };
    }

    public function acknowledge(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'Acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function resolve(): void
    {
        $this->update(['status' => 'Resolved']);
    }
}
