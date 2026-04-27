<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'resource_type',
        'filter_criteria',
        'user_id',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'filter_criteria' => 'array',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForResource($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
