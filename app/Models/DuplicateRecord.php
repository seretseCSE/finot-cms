<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'primary_record_id',
        'duplicate_record_id',
        'match_criteria',
        'status',
        'merged_at',
        'merged_by',
        'notes',
    ];

    protected $casts = [
        'match_criteria' => 'array',
        'merged_at' => 'datetime',
    ];

    public function mergedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeMerged($query)
    {
        return $query->where('status', 'merged');
    }

    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    public function markAsMerged(int $userId): void
    {
        $this->update([
            'status' => 'merged',
            'merged_at' => now(),
            'merged_by' => $userId,
        ]);
    }

    public function markAsIgnored(): void
    {
        $this->update([
            'status' => 'ignored',
        ]);
    }
}
