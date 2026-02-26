<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberGroupAssignment extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $table = 'member_group_assignments';

    protected $fillable = [
        'member_id',
        'group_id',
        'effective_from',
        'effective_to',
        'assigned_by',
        'removed_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * Get the member
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the group
     */
    public function group()
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    /**
     * Get user who assigned
     */
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get user who removed
     */
    public function remover()
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /**
     * Check if assignment is currently active
     */
    public function isActive(): bool
    {
        return is_null($this->effective_to);
    }

    /**
     * Scope active assignments
     */
    public function scopeActive($query)
    {
        return $query->whereNull('effective_to');
    }

    /**
     * Scope historical assignments
     */
    public function scopeHistorical($query)
    {
        return $query->whereNotNull('effective_to');
    }

    /**
     * Scope for specific member
     */
    public function scopeForMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope for specific group
     */
    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Get current assignment for a member
     */
    public static function getCurrentAssignment($memberId)
    {
        return static::forMember($memberId)
            ->active()
            ->with('group')
            ->first();
    }

    /**
     * Get assignment history for a member
     */
    public static function getAssignmentHistory($memberId, $limit = 10)
    {
        return static::forMember($memberId)
            ->with(['group', 'assigner', 'remover'])
            ->latest('effective_from')
            ->limit($limit)
            ->get();
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($assignment) {
            // Log group assignment to audit trail
            $assignment->logToAuditTrail('group_assigned', [
                'old_value' => null,
                'new_value' => [
                    'group_id' => $assignment->group_id,
                    'group_name' => $assignment->group->name,
                    'effective_from' => $assignment->effective_from,
                ],
            ]);
        });

        static::updated(function ($assignment) {
            // Check if this is a removal (effective_to was set)
            if ($assignment->wasChanged('effective_to') && !is_null($assignment->effective_to)) {
                $assignment->logToAuditTrail('group_removed', [
                    'old_value' => [
                        'group_name' => $assignment->group->name,
                    ],
                    'new_value' => [
                        'effective_to' => $assignment->effective_to,
                    ],
                ]);
            }
        });
    }

    /**
     * Log to audit trail
     */
    private function logToAuditTrail($action, $data)
    {
        // This would integrate with your existing audit trail system
        // For now, we'll just log to Laravel's default logging
        \Log::info("Group Assignment Audit: {$action}", [
            'member_id' => $this->member_id,
            'group_id' => $this->group_id,
            'data' => $data,
            'user_id' => auth()->id(),
        ]);
    }
}
