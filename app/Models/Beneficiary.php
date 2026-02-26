<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beneficiary extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'beneficiary_code',
        'full_name',
        'phone',
        'address',
        'type',
        'need_category',
        'email',
        'id_number',
        'dependents_count',
        'monthly_income',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'dependents_count' => 'integer',
        'monthly_income' => 'decimal:2',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function aidDistributions(): HasMany
    {
        return $this->hasMany(AidDistribution::class);
    }

    /**
     * Get total aid received
     */
    public function getTotalAidReceivedAttribute(): float
    {
        return $this->aidDistributions()->sum('amount');
    }

    /**
     * Get last distribution date
     */
    public function getLastDistributionDateAttribute(): ?string
    {
        $lastDistribution = $this->aidDistributions()->latest('distribution_date')->first();
        
        if (!$lastDistribution) {
            return null;
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($lastDistribution->distribution_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($lastDistribution->distribution_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($lastDistribution->distribution_date)['year'];
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'Completed']);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'beneficiary_completed',
            'entity_id' => $this->id,
            'entity_type' => 'beneficiary',
            'old_value' => json_encode(['status' => 'Active']),
            'new_value' => json_encode(['status' => 'Completed']),
            'user_id' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Check if can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->aidDistributions()->exists();
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Beneficiaries';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Charity';
    }
}
