<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentModel extends BaseModel
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $table = 'parents';

    protected $fillable = [
        'full_name',
        'phone',
        'relationship_type',
        'member_count',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'member_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($parent) {
            // Update member count when relationships change
            $parent->updateMemberCount();
        });

        static::deleted(function ($parent) {
            // Soft delete - set is_active to false instead
            $parent->is_active = false;
            $parent->save();
        });
    }

    /**
     * Get linked members through member_parent_guardians
     */
    public function members()
    {
        return $this->hasManyThrough(
            Member::class,
            MemberParentGuardian::class,
            'parent_id',
            'id',
            'id',
            'member_id'
        );
    }

    /**
     * Get parent guardian relationships
     */
    public function parentGuardians()
    {
        return $this->hasMany(MemberParentGuardian::class, 'parent_id');
    }

    /**
     * Update member count
     */
    public function updateMemberCount()
    {
        $this->member_count = $this->members()->count();
        $this->saveQuietly();
    }

    /**
     * Check if parent can be deleted
     */
    public function canBeDeleted(): bool
    {
        // Check if linked to any active members
        return !$this->members()->whereIn('status', ['Active', 'Member'])->exists();
    }

    /**
     * Get active members only
     */
    public function activeMembers()
    {
        return $this->members()->whereIn('status', ['Active', 'Member']);
    }

    /**
     * Scope active parents
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by phone
     */
    public function scopeByPhone($query, $phone)
    {
        return $query->where('phone', $phone);
    }

    /**
     * Find or create parent by phone
     */
    public static function findOrCreateByPhone($phone, $name, $relationship = null)
    {
        $parent = static::byPhone($phone)->first();
        
        if (!$parent) {
            $parent = static::create([
                'full_name' => $name,
                'phone' => $phone,
                'relationship_type' => $relationship,
            ]);
        }
        
        return $parent;
    }

    /**
     * Get resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'parents';
    }

    /**
     * Get navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Parents / ወላጆች';
    }

    /**
     * Get navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-heart';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Membership';
    }
}
