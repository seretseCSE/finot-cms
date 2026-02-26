<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContributionAmount extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'group_id',
        'month_name',
        'amount',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this amount is currently active
     */
    public function isCurrentlyActive(): bool
    {
        $today = now()->toDateString();
        
        return $this->effective_from <= $today && 
               (is_null($this->effective_to) || $this->effective_to >= $today);
    }

    /**
     * Check if this amount overlaps with another date range
     */
    public function overlapsWith(?ContributionAmount $other): bool
    {
        if (!$other || $this->id === $other->id) {
            return false;
        }

        if ($this->group_id !== $other->group_id || $this->month_name !== $other->month_name) {
            return false;
        }

        $thisStart = $this->effective_from;
        $thisEnd = $this->effective_to ?? '9999-12-31';
        $otherStart = $other->effective_from;
        $otherEnd = $other->effective_to ?? '9999-12-31';

        return !($thisEnd < $otherStart || $thisStart > $otherEnd);
    }

    /**
     * Check if this amount can be deleted (no contributions recorded)
     */
    public function canBeDeleted(): bool
    {
        // This will be implemented when we create the contributions table
        // For now, return true to allow deletion during development
        return true;
    }

    /**
     * Scope to get currently active amounts
     */
    public function scopeActive($query)
    {
        $today = now()->toDateString();
        
        return $query->where('effective_from', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', $today);
                    });
    }

    /**
     * Scope to get amounts for a specific group
     */
    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Scope to get amounts for a specific month
     */
    public function scopeForMonth($query, $monthName)
    {
        return $query->where('month_name', $monthName);
    }

    /**
     * Get status label for display
     */
    public function getStatusAttribute(): string
    {
        return $this->isCurrentlyActive() ? 'Current' : 'Historical';
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Birr ' . number_format($this->amount, 2);
    }

    /**
     * Validate no overlapping periods for same group and month
     */
    public static function validateNoOverlap(
        int $groupId, 
        string $monthName, 
        string $effectiveFrom, 
        ?string $effectiveTo = null, 
        ?int $excludeId = null
    ): array {
        $query = static::where('group_id', $groupId)
                     ->where('month_name', $monthName);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingAmounts = $query->get();

        foreach ($existingAmounts as $existing) {
            $newStart = $effectiveFrom;
            $newEnd = $effectiveTo ?? '9999-12-31';
            $existingStart = $existing->effective_from;
            $existingEnd = $existing->effective_to ?? '9999-12-31';

            // Check for overlap
            if (!($newEnd < $existingStart || $newStart > $existingEnd)) {
                return [
                    'valid' => false,
                    'message' => "An amount is already defined for this group in {$monthName} from {$existing->effective_from} to " . 
                               ($existing->effective_to ?? 'indefinite')
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get the resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'contribution_amounts';
    }

    /**
     * Get the navigation label for the resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Contribution Settings';
    }

    /**
     * Get the navigation icon for the resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
    }

    /**
     * Get the navigation group for the resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }
}
