<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Donation extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'donor_name',
        'amount',
        'donation_date',
        'donation_type',
        'custom_donation_type',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'donation_date' => 'date',
    ];

    protected $dates = [
        'donation_date',
    ];

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get formatted donor name (Anonymous if null)
     */
    public function getFormattedDonorNameAttribute(): string
    {
        return $this->donor_name ?: 'Anonymous';
    }

    /**
     * Get formatted donation type
     */
    public function getFormattedDonationTypeAttribute(): string
    {
        if ($this->donation_type === 'Other' && $this->custom_donation_type) {
            return $this->custom_donation_type;
        }
        
        return $this->donation_type;
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Birr ' . number_format($this->amount, 2);
    }

    /**
     * Get Ethiopian formatted donation date
     */
    public function getEthiopianDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->donation_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->donation_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->donation_date)['year'];
    }

    /**
     * Scope to get donations by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('donation_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get donations by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('donation_type', $type);
    }

    /**
     * Get total donations for date range
     */
    public static function getTotalForDateRange($startDate, $endDate): float
    {
        return static::dateRange($startDate, $endDate)->sum('amount');
    }

    /**
     * Get total donations by type for date range
     */
    public static function getTotalByTypeForDateRange($startDate, $endDate): array
    {
        return static::dateRange($startDate, $endDate)
                    ->selectRaw('donation_type, SUM(amount) as total')
                    ->groupBy('donation_type')
                    ->pluck('total', 'donation_type')
                    ->toArray();
    }

    /**
     * Get monthly donation totals for the year
     */
    public static function getMonthlyTotalsForYear($year): array
    {
        $startOfYear = "{$year}-01-01";
        $endOfYear = "{$year}-12-31";
        
        return static::dateRange($startOfYear, $endOfYear)
                    ->selectRaw('MONTH(donation_date) as month, SUM(amount) as total')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month')
                    ->toArray();
    }

    /**
     * Check if donation can be deleted (superadmin only)
     */
    public function canBeDeleted(): bool
    {
        // Donations can only be deleted by superadmin
        // and must be logged to Tier-2 audit trail
        return true; // Permission check handled in resource
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'donations';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Donations';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }
}
