<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundraisingCampaign extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'target_amount',
        'total_raised',
        'start_date',
        'end_date',
        'description',
        'featured_image',
        'category',
        'bank_account_info',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'total_raised' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $dates = [
        'start_date',
        'end_date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get formatted start date in Ethiopian
     */
    public function getEthiopianStartDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->start_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->start_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->start_date)['year'];
    }

    /**
     * Get formatted end date in Ethiopian
     */
    public function getEthiopianEndDateAttribute(): ?string
    {
        if (!$this->end_date) {
            return null;
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['year'];
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount == 0) {
            return 0;
        }

        return round(($this->total_raised / $this->target_amount) * 100, 2);
    }

    /**
     * Get days remaining
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->end_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->end_date));
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Draft' => 'gray',
            'Active' => 'blue',
            'Completed' => 'green',
            'Cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Update total raised amount
     */
    public function updateTotalRaised(float $amount, ?User $user = null): void
    {
        $oldAmount = $this->total_raised;
        $this->update([
            'total_raised' => $amount,
            'updated_by' => $user ? $user->id : auth()->id(),
        ]);

        // Log to Tier 2 audit trail
        Log::channel('audit')->info('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'fundraising_total_updated',
            'entity_id' => $this->id,
            'entity_type' => 'fundraising_campaign',
            'old_value' => json_encode(['total_raised' => $oldAmount]),
            'new_value' => json_encode(['total_raised' => $amount]),
            'user_id' => $user ? $user->id : auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'fundraising_campaigns';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Fundraising Campaigns';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-heart';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Events & Fundraising';
    }
}
