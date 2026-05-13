<?php

namespace App\Models;

use App\Helpers\EthiopianDateHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FundraisingCampaign extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check() && ! $model->created_by) {
                $model->created_by = Auth::id();
            }
            if (Auth::check() && ! $model->updated_by) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::saving(function ($model) {
            if ($model->start_date && $model->end_date && $model->end_date < $model->start_date) {
                throw new \Illuminate\Validation\ValidationException(
                    validator()->make([], []),
                    new \Illuminate\Support\MessageBag(['end_date' => 'End date must be on or after the start date.'])
                );
            }
        });
    }

    /**
     * Handle additional amount by adding it to the existing total
     */
    public function setAdditionalAmountAttribute($value)
    {
        // Only process if this is an update and value is provided
        if ($this->exists && $value > 0) {
            \DB::transaction(function () use ($value) {
                // Ensure current_total_raised is a number, not an array
                $currentTotal = is_array($this->total_raised) ? 0 : $this->total_raised;
                $this->attributes['total_raised'] = $currentTotal + $value;

                // Create a donation record for audit trail
                try {
                    Donation::create([
                        'donor_name' => 'Campaign Update',
                        'amount' => $value,
                        'donation_date' => now(),
                        'donation_type' => $this->campaign_category ?? 'Other',
                        'notes' => 'Added via fundraising campaign admin',
                        'recorded_by' => Auth::id(),
                        'fundraising_campaign_id' => $this->id,
                    ]);
                } catch (\Exception $e) {
                    // Silently fail if donation can't be created — total_raised still updated
                    \Log::warning('Could not create donation record for campaign update: '.$e->getMessage());
                }
            });
        }
    }

    /**
     * Handle manual total override (for error correction)
     */
    public function setManualTotalRaisedAttribute($value)
    {
        // Only process if this is an update and value is provided
        if ($this->exists && $value !== null && $value >= 0) {
            $this->attributes['total_raised'] = $value;
        }
    }

    protected $fillable = [
        'campaign_name',
        'target_amount',
        'total_raised',
        'additional_amount',
        'manual_total_raised',
        'start_date',
        'end_date',
        'description',
        'featured_image',
        'campaign_category',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->target_amount == 0) {
            return 0;
        }

        return min(100, ($this->total_raised / $this->target_amount) * 100);
    }

    public function getDaysRemainingAttribute()
    {
        if (! $this->end_date) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->end_date, false));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeVisible($query)
    {
        return $query->whereIn('status', ['Active', 'Completed']);
    }

    /**
     * Get formatted start date in Ethiopian calendar
     */
    public function getFormattedStartDateAttribute(): string
    {
        return app(EthiopianDateHelper::class)
            ->toEthiopian($this->start_date)['month_name_am'].' '.
            app(EthiopianDateHelper::class)
                ->toEthiopian($this->start_date)['day'].', '.
            app(EthiopianDateHelper::class)
                ->toEthiopian($this->start_date)['year'];
    }

    /**
     * Get full URL for featured image.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (!$this->featured_image) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (filter_var($this->featured_image, FILTER_VALIDATE_URL)) {
            return $this->featured_image;
        }

        // If it starts with 'fundraising-campaigns/', it's in storage
        if (str_starts_with($this->featured_image, 'fundraising-campaigns/')) {
            return Storage::url($this->featured_image);
        }

        // Fallback to asset
        return asset($this->featured_image);
    }

    /**
     * Get formatted end date in Ethiopian
     */
    public function getEthiopianEndDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['month_name_am'] . ' ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['year'];
    }

    /**
     * Get formatted end date in Ethiopian calendar
     */
    public function getFormattedEndDateAttribute(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        return app(EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['month_name_am'].' '.
            app(EthiopianDateHelper::class)
                ->toEthiopian($this->end_date)['day'].', '.
            app(EthiopianDateHelper::class)
                ->toEthiopian($this->end_date)['year'];
    }

    public function donations()
    {
        return $this->hasMany(Donation::class, 'fundraising_campaign_id');
    }

    /**
     * Get resource name for permissions
     */
    public static function getPermissionName($action): string
    {
        return 'fundraising_campaigns.'.$action;
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
        return 'heroicon-o-banknotes';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Events';
    }

    /**
     * Update total raised based on sum of donations
     */
    public function updateTotalRaised(): void
    {
        $donationCount = $this->donations()->count();
        \Log::info('Updating campaign total', [
            'campaign_id' => $this->id,
            'donation_count' => $donationCount,
        ]);

        $total = $this->donations()
            ->where('donor_name', '!=', 'Campaign Update')
            ->sum('amount');

        \Log::info('Calculated total', ['campaign_id' => $this->id, 'total' => $total]);

        $this->update(['total_raised' => $total]);

        \Log::info('Campaign total updated in DB', ['campaign_id' => $this->id, 'new_total' => $this->fresh()->total_raised]);
    }
}
