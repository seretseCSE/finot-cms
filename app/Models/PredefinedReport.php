<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredefinedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'resource_type',
        'filter_criteria',
        'columns',
        'format',
        'is_active',
        'created_by',
        'display_order',
    ];

    protected $casts = [
        'filter_criteria' => 'array',
        'columns' => 'array',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForResource($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public static function getActiveForResource(string $resourceType): array
    {
        return static::forResource($resourceType)
            ->active()
            ->ordered()
            ->get()
            ->toArray();
    }
}
