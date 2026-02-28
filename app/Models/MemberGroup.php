<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberGroup extends BaseModel
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $table = 'member_groups';

    protected $fillable = [
        'name',
        'group_type',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($group) {
            if (!$group->created_by && auth()->check()) {
                $group->created_by = auth()->id();
            }
        });

        static::deleting(function ($group) {
            // Check for active assignments before allowing delete
            if ($group->assignments()->whereNull('effective_to')->exists()) {
                throw new \Exception('Cannot delete group with active member assignments. Members must be removed first.');
            }
        });
    }

    /**
     * Get assignments for this group
     */
    public function assignments()
    {
        return $this->hasMany(MemberGroupAssignment::class, 'group_id');
    }

    /**
     * Get active assignments only
     */
    public function activeAssignments()
    {
        return $this->assignments()->whereNull('effective_to');
    }

    /**
     * Get all members (current and historical)
     */
    public function members()
    {
        return $this->hasManyThrough(
            Member::class,
            MemberGroupAssignment::class,
            'group_id',
            'id',
            'id',
            'member_id'
        );
    }

    /**
     * Get current active members
     */
    public function activeMembers()
    {
        return $this->members()->whereNull('member_group_assignments.effective_to');
    }

    /**
     * Get active member count
     */
    public function getActiveMemberCountAttribute(): int
    {
        return $this->activeAssignments()->count();
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('group_type', $type);
    }

    /**
     * Check if group can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->activeAssignments()->exists();
    }

    /**
     * Assign member to group
     */
    public function assignMember($memberId, $effectiveFromDate = null, $assignedBy = null)
    {
        // Check if member already has active assignment
        $existingAssignment = MemberGroupAssignment::where('member_id', $memberId)
            ->whereNull('effective_to')
            ->first();

        if ($existingAssignment) {
            throw new \Exception("Member is already assigned to group: {$existingAssignment->group->name}. Remove them first.");
        }

        return MemberGroupAssignment::create([
            'member_id' => $memberId,
            'group_id' => $this->id,
            'effective_from' => $effectiveFromDate ?? now()->toDateString(),
            'assigned_by' => $assignedBy ?? auth()->user()->id(),
        ]);
    }

    /**
     * Remove member from group
     */
    public function removeMember($memberId, $removedBy = null)
    {
        $assignment = MemberGroupAssignment::where('member_id', $memberId)
            ->where('group_id', $this->id)
            ->whereNull('effective_to')
            ->first();

        if (!$assignment) {
            throw new \Exception('Member is not currently assigned to this group.');
        }

        $assignment->update([
            'effective_to' => now()->toDateString(),
            'removed_by' => $removedBy ?? auth()->user()->id(),
        ]);

        return $assignment;
    }

    /**
     * Get resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'member_groups';
    }

    /**
     * Get navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Member Groups / የአባላት ቡድሮች';
    }

    /**
     * Get navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    /**
     * Get navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Membership';
    }
}
