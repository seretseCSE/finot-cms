<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\GeneratesAutoId;
use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupAssignment extends BaseModel
{
    use HasFactory, ScopedByDepartment, HasAuditLog, GeneratesAutoId;

    protected $fillable = [
        'member_id',
        'group_id',
        'assigned_date',
        'assigned_by',
        'role_in_group',
        'department_id',
        'is_active',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'is_active' => 'boolean',
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
     * Get the department for this assignment.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
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
