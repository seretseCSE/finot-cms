<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CustomOption extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'field_name',
        'option_value',
        'status',
        'added_by',
        'usage_count',
    ];

    protected $casts = [
        'status' => 'string',
        'usage_count' => 'integer',
    ];

    /**
     * Get the user who added this option.
     */
    public function addedByUser()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Scope a query to only include options with a specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include options for a specific field.
     */
    public function scopeForField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    /**
     * Scope a query to only include approved options.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include pending options.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include rejected options.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Increment the usage count.
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement the usage count.
     */
    public function decrementUsage()
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }

    /**
     * Get all available field names.
     */
    public static function getFieldNames(): array
    {
        return static::distinct('field_name')
            ->orderBy('field_name')
            ->pluck('field_name')
            ->toArray();
    }

    /**
     * Get approved options for a specific field.
     */
    public static function getApprovedOptions(string $fieldName): array
    {
        return static::forField($fieldName)
            ->approved()
            ->orderBy('option_value')
            ->pluck('option_value', 'id')
            ->toArray();
    }

    /**
     * Check if option can be deleted (usage count must be 0).
     */
    public function canBeDeleted(): bool
    {
        return $this->usage_count === 0;
    }
}
