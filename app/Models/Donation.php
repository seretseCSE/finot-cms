<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Donation extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function ($donation) {
            try {
                \DB::transaction(function () use ($donation) {
                    if ($donation->bank_account_id && $donation->bankAccount) {
                        $donation->bankAccount->updateBalance();
                    }

                    if ($donation->fundraising_campaign_id && $donation->fundraisingCampaign) {
                        $donation->fundraisingCampaign->updateTotalRaised();
                    }
                });
            } catch (\Exception $e) {
                \Log::error('Donation created event failed', [
                    'donation_id' => $donation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::updated(function ($donation) {
            try {
                \DB::transaction(function () use ($donation) {
                    if ($donation->bank_account_id && $donation->bankAccount) {
                        $donation->bankAccount->updateBalance();
                    }

                    if ($donation->fundraising_campaign_id && $donation->wasChanged('amount') && $donation->fundraisingCampaign) {
                        $donation->fundraisingCampaign->updateTotalRaised();
                    }
                });
            } catch (\Exception $e) {
                \Log::error('Donation updated event failed', [
                    'donation_id' => $donation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::deleted(function ($donation) {
            try {
                \DB::transaction(function () use ($donation) {
                    if ($donation->bank_account_id && $donation->bankAccount) {
                        $donation->bankAccount->updateBalance();
                    }

                    if ($donation->fundraising_campaign_id && $donation->fundraisingCampaign) {
                        $donation->fundraisingCampaign->updateTotalRaised();
                    }
                });
            } catch (\Exception $e) {
                \Log::error('Donation deleted event failed', [
                    'donation_id' => $donation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    protected $fillable = [
        'donor_name',
        'amount',
        'donation_date',
        'donation_type',
        'custom_donation_type',
        'notes',
        'recorded_by',
        'bank_account_id',
        'fundraising_campaign_id',
    ];

    /**
     * Validation rules for donation creation and updates.
     */
    public static function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
            ],
            'donation_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'before_or_equal:today',
            ],
            'donation_type' => [
                'required',
                'string',
                'max:255',
            ],
            'donor_name' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

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

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function fundraisingCampaign()
    {
        return $this->belongsTo(FundraisingCampaign::class, 'fundraising_campaign_id');
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
        return 'Birr '.number_format($this->amount, 2);
    }

    /**
     * Get Ethiopian formatted donation date
     */
    public function getEthiopianDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->donation_date)['month_name_am'].' '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->donation_date)['day'].', '.
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
     * Validate donation amount bounds
     */
    public function validateAmountBounds(): array
    {
        $errors = [];

        if ($this->amount < 0.01) {
            $errors[] = [
                'field' => 'amount',
                'message' => 'Donation amount must be at least 0.01 Birr.',
                'code' => 'min_amount'
            ];
        }

        if ($this->amount > 999999.99) {
            $errors[] = [
                'field' => 'amount',
                'message' => 'Donation amount cannot exceed 999,999.99 Birr.',
                'code' => 'max_amount'
            ];
        }

        return $errors;
    }

    /**
     * Check if donation amount is within acceptable range
     */
    public function isAmountValid(): bool
    {
        return $this->amount >= 0.01 && $this->amount <= 999999.99;
    }

    /**
     * Get donation amount validation rules for forms
     */
    public static function getAmountValidationRules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/' // Ensure proper decimal format
            ],
        ];
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
