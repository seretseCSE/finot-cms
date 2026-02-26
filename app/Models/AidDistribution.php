<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AidDistribution extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'beneficiary_id',
        'distribution_date',
        'aid_type',
        'amount',
        'distributed_by',
        'receipt_number',
        'notes',
        'is_locked',
        'locked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'distribution_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    protected $dates = [
        'distribution_date',
        'locked_at',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function distributedBy()
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    /**
     * Get formatted distribution date in Ethiopian
     */
    public function getEthiopianDistributionDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->distribution_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->distribution_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->distribution_date)['year'];
    }

    /**
     * Check if distribution can be edited
     */
    public function canBeEdited(?User $user = null): bool
    {
        if ($this->is_locked) {
            // Only charity_head can unlock
            return $user && $user->role === 'charity_head';
        }

        // Distribution date cannot be in the future
        return $this->distribution_date <= now()->toDateString();
    }

    /**
     * Lock distribution
     */
    public function lock(?User $user = null): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'aid_distribution_locked',
            'entity_id' => $this->id,
            'entity_type' => 'aid_distribution',
            'old_value' => json_encode(['is_locked' => false]),
            'new_value' => json_encode(['is_locked' => true]),
            'user_id' => $user?->id ?? auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Unlock distribution (charity_head only)
     */
    public function unlock(?User $user = null): void
    {
        if (!$user || $user->role !== 'charity_head') {
            throw new \Exception('Only Charity Head can unlock distributions');
        }

        $this->update([
            'is_locked' => false,
            'locked_at' => null,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'aid_distribution_unlocked',
            'entity_id' => $this->id,
            'entity_type' => 'aid_distribution',
            'old_value' => json_encode(['is_locked' => true]),
            'new_value' => json_encode(['is_locked' => false]),
            'user_id' => $user->id,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'aid_distributions';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Aid Distributions';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-hand-raised';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Charity';
    }
}
