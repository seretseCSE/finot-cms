<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberParentGuardian extends BaseModel
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'member_id',
        'parent_id',
        'parent_name',
        'relationship',
        'phone',
        'is_external',
    ];

    protected $casts = [
        'is_external' => 'boolean',
    ];

    /**
     * Get the member that owns this parent/guardian.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the parent record if linked
     */
    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    /**
     * Get display name (prefer parent record name, fallback to stored name)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->parent_id && $this->parent) {
            return $this->parent->full_name;
        }
        
        return $this->parent_name;
    }

    /**
     * Get display phone (prefer parent record phone, fallback to stored phone)
     */
    public function getDisplayPhoneAttribute(): string
    {
        if ($this->parent_id && $this->parent) {
            return $this->parent->phone;
        }
        
        return $this->phone;
    }

    /**
     * Scope external parents only
     */
    public function scopeExternal($query)
    {
        return $query->where('is_external', true);
    }

    /**
     * Scope linked parents only
     */
    public function scopeLinked($query)
    {
        return $query->where('is_external', false)->whereNotNull('parent_id');
    }

    /**
     * Create or link parent based on phone
     */
    public static function createOrLinkParent($memberId, $name, $phone, $relationship)
    {
        // Try to find existing parent by phone
        $parent = ParentModel::byPhone($phone)->first();
        
        if ($parent) {
            // Link to existing parent
            return static::create([
                'member_id' => $memberId,
                'parent_id' => $parent->id,
                'parent_name' => $name,
                'relationship' => $relationship,
                'phone' => $phone,
                'is_external' => false,
            ]);
        } else {
            // Create external parent entry
            return static::create([
                'member_id' => $memberId,
                'parent_name' => $name,
                'relationship' => $relationship,
                'phone' => $phone,
                'is_external' => true,
            ]);
        }
    }
}
