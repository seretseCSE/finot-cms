<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\GeneratesAutoId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupAssignment extends BaseModel
{
    use HasFactory, HasAuditLog, GeneratesAutoId;

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
     * Get the member who is assigned to this group.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the group for this assignment.
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who made this assignment.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'group_assignments';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Group Assignments';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Member Management';
    }
}
