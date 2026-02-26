<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'title',
        'title_am',
        'content',
        'content_am',
        'start_date',
        'end_date',
        'is_urgent',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_urgent' => 'boolean',
    ];

    protected $dates = [
        'start_date',
        'end_date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Active' => 'green',
            'Expired' => 'red',
            'Archived' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Check if announcement is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'Active' &&
               $this->start_date->lte(now()) &&
               (!$this->end_date || $this->end_date->gte(now()));
    }

    /**
     * Check if announcement should be expired
     */
    public function shouldExpire(): bool
    {
        return $this->status === 'Active' &&
               $this->end_date &&
               $this->end_date->lt(now());
    }

    /**
     * Expire the announcement
     */
    public function expire(): void
    {
        $this->update(['status' => 'Expired']);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'announcement_expired',
            'entity_id' => $this->id,
            'entity_type' => 'announcement',
            'old_value' => json_encode(['status' => 'Active']),
            'new_value' => json_encode(['status' => 'Expired']),
            'user_id' => 'system',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'announcements';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Announcements';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}
