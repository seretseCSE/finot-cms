<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LossRecord extends Model
{
    protected $fillable = [
        'item_id',
        'loss_type',
        'quantity',
        'reason',
        'loss_date',
        'reference_number',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'loss_date' => 'date',
        'quantity' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getLossTypeColorAttribute(): string
    {
        return match($this->loss_type) {
            'Lost' => 'danger',
            'Damaged' => 'warning',
            'Disposed' => 'gray',
            default => 'gray',
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($record) {
            // Update item status based on loss type
            $item = InventoryItem::find($record->item_id);
            if ($item) {
                $item->update([
                    'status' => $record->loss_type,
                    'quantity' => $item->quantity - $record->quantity,
                ]);

                // Also create an inventory movement record
                InventoryMovement::create([
                    'item_id' => $record->item_id,
                    'movement_type' => 'Stock Out',
                    'sub_type' => match($record->loss_type) {
                        'Lost' => 'Loss',
                        'Damaged' => 'Loss',
                        'Disposed' => 'Loss',
                        default => 'Loss',
                    },
                    'quantity' => $record->quantity,
                    'movement_date' => $record->loss_date,
                    'reference_number' => $record->reference_number,
                    'notes' => $record->reason . ($record->notes ? ' | ' . $record->notes : ''),
                    'recorded_by' => $record->recorded_by,
                ]);
            }
        });
    }
}
