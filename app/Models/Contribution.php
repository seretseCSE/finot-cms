<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contribution extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'member_id',
        'academic_year_id',
        'amount',
        'month_name',
        'payment_date',
        'payment_method',
        'custom_payment_method',
        'notes',
        'recorded_by',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    protected $dates = [
        'payment_date',
        'archived_at',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Archive this contribution
     */
    public function archive(): void
    {
        $this->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }

    /**
     * Unarchive this contribution
     */
    public function unarchive(): void
    {
        $this->update([
            'is_archived' => false,
            'archived_at' => null,
        ]);
    }

    /**
     * Scope to get non-archived contributions
     */
    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope to get archived contributions
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope to get contributions for a specific member and academic year
     */
    public function scopeForMemberAndYear($query, $memberId, $academicYearId)
    {
        return $query->where('member_id', $memberId)
                   ->where('academic_year_id', $academicYearId);
    }

    /**
     * Scope to get contributions for a specific month
     */
    public function scopeForMonth($query, $monthName)
    {
        return $query->where('month_name', $monthName);
    }

    /**
     * Get total amount paid for member in academic year
     */
    public static function getTotalForMemberInYear($memberId, $academicYearId): float
    {
        return static::forMemberAndYear($memberId, $academicYearId)
                    ->notArchived()
                    ->sum('amount');
    }

    /**
     * Get total amount paid for member in academic year for specific month
     */
    public static function getTotalForMemberInYearForMonth($memberId, $academicYearId, $monthName): float
    {
        return static::forMemberAndYear($memberId, $academicYearId)
                    ->forMonth($monthName)
                    ->notArchived()
                    ->sum('amount');
    }

    /**
     * Check if member has any contribution for specific month in academic year
     */
    public static function hasContributionForMonth($memberId, $academicYearId, $monthName): bool
    {
        return static::forMemberAndYear($memberId, $academicYearId)
                   ->forMonth($monthName)
                   ->notArchived()
                   ->exists();
    }

    /**
     * Get expected amount for member's group and month
     */
    public static function getExpectedAmountForMemberAndMonth($memberId, $monthName): ?float
    {
        $member = Member::find($memberId);
        if (!$member || !$member->member_group_id) {
            return null;
        }

        $contributionAmount = ContributionAmount::where('group_id', $member->member_group_id)
                                           ->forMonth($monthName)
                                           ->active()
                                           ->first();

        return $contributionAmount ? $contributionAmount->amount : null;
    }

    /**
     * Check if amount is unusual compared to expected
     */
    public static function isAmountUnusual($memberId, $monthName, $amount): bool
    {
        $expectedAmount = static::getExpectedAmountForMemberAndMonth($memberId, $monthName);
        
        if (!$expectedAmount) {
            return false; // Can't determine if unusual without expected amount
        }

        $difference = abs($amount - $expectedAmount);
        $percentageDifference = ($difference / $expectedAmount) * 100;

        return $percentageDifference > 50; // More than 50% difference
    }

    /**
     * Get formatted payment method
     */
    public function getFormattedPaymentMethodAttribute(): string
    {
        if ($this->payment_method === 'Other' && $this->custom_payment_method) {
            return $this->custom_payment_method;
        }
        
        return $this->payment_method;
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Birr ' . number_format($this->amount, 2);
    }

    /**
     * Check if contribution can be deleted (admin/superadmin only)
     */
    public function canBeDeleted(): bool
    {
        // Contributions can only be deleted by admin/superadmin
        // and must be logged to Tier-2 audit trail
        return true; // Permission check handled in resource
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'contributions';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Contributions';
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
        return 'Finance';
    }
}
