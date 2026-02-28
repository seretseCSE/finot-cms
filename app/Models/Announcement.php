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
        'is_global',
        'target_audience',
        'broadcast_channels',
        'acknowledged_by',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_urgent' => 'boolean',
        'is_global' => 'boolean',
        'broadcast_channels' => 'array',
        'acknowledged_by' => 'array',
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
     * Check if announcement is global
     */
    public function isGlobalAnnouncement(): bool
    {
        return $this->is_global === true;
    }

    /**
     * Get target audience options
     */
    public static function getTargetAudienceOptions(): array
    {
        return [
            'all_users' => 'All Users',
            'admin_only' => 'Admin Only',
            'department_heads' => 'Department Heads',
            'specific_departments' => 'Specific Departments',
            'specific_roles' => 'Specific Roles',
        ];
    }

    /**
     * Get broadcast channel options
     */
    public static function getBroadcastChannelOptions(): array
    {
        return [
            'in_app' => 'In-App Notification',
            'email' => 'Email',
            'push_notification' => 'Push Notification',
        ];
    }

    /**
     * Check if user has acknowledged this announcement
     */
    public function isAcknowledgedBy($userId): bool
    {
        if (!$this->acknowledged_by) {
            return false;
        }
        
        return in_array($userId, $this->acknowledged_by);
    }

    /**
     * Mark announcement as acknowledged by user
     */
    public function acknowledgeBy($userId): void
    {
        $acknowledged = $this->acknowledged_by ?? [];
        
        if (!in_array($userId, $acknowledged)) {
            $acknowledged[] = $userId;
            $this->update(['acknowledged_by' => $acknowledged]);
        }
    }

    /**
     * Get unacknowledged count for global announcements
     */
    public function getUnacknowledgedCount(): int
    {
        if (!$this->isGlobalAnnouncement()) {
            return 0;
        }

        $totalUsers = \App\Models\User::count();
        $acknowledgedCount = count($this->acknowledged_by ?? []);
        
        return $totalUsers - $acknowledgedCount;
    }

    /**
     * Broadcast the global announcement to target users
     */
    public function broadcast(): void
    {
        if (!$this->isGlobalAnnouncement()) {
            return;
        }

        $channels = $this->broadcast_channels ?? ['in_app'];
        
        // Get target users based on audience
        $query = \App\Models\User::query();
        
        match($this->target_audience) {
            'admin_only' => $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'superadmin']);
            }),
            'department_heads' => $query->whereHas('roles', function ($q) {
                $q->where('name', 'like', '%_head');
            }),
            'specific_departments' => $query->whereIn('department_id', $this->specific_departments ?? []),
            'specific_roles' => $query->whereHas('roles', function ($q) {
                $q->whereIn('name', $this->specific_roles ?? []);
            }),
            default => null, // all_users - no filtering
        };

        $users = $query->get();

        // Send notifications based on selected channels
        foreach ($users as $user) {
            $notificationChannels = [];
            
            if (in_array('in_app', $channels)) {
                $notificationChannels[] = 'database';
            }
            
            if (in_array('email', $channels)) {
                $notificationChannels[] = 'mail';
            }

            if (!empty($notificationChannels)) {
                $user->notify(new \App\Notifications\GlobalAnnouncementNotification($this, $notificationChannels));
            }
        }

        // Log the broadcast
        \Log::channel('audit')->info('Global Announcement Broadcast', [
            'announcement_id' => $this->id,
            'title' => $this->title,
            'target_audience' => $this->target_audience,
            'channels' => $channels,
            'users_count' => $users->count(),
            'broadcast_by' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get users who need to acknowledge this announcement
     */
    public function getUsersNeedingAcknowledgment(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->isGlobalAnnouncement()) {
            return collect();
        }

        $query = \App\Models\User::query();
        
        // Filter by target audience
        match($this->target_audience) {
            'admin_only' => $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'superadmin']);
            }),
            'department_heads' => $query->whereHas('roles', function ($q) {
                $q->where('name', 'like', '%_head');
            }),
            'specific_departments' => $query->whereIn('department_id', $this->specific_departments ?? []),
            'specific_roles' => $query->whereHas('roles', function ($q) {
                $q->whereIn('name', $this->specific_roles ?? []);
            }),
            default => null, // all_users - no filtering
        };

        // Exclude users who have already acknowledged
        $acknowledgedIds = $this->acknowledged_by ?? [];
        if (!empty($acknowledgedIds)) {
            $query->whereNotIn('id', $acknowledgedIds);
        }

        return $query->get();
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
