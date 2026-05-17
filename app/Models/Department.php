<?php

namespace App\Models;

use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    use ScopedByDepartment;

    protected $fillable = [
        'name_en',
        'name_am',
        'description',
        'head_user_id',
        'head_image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the users in this department.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the department head user.
     */
    public function headUser()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * Get the head user's name.
     */
    public function headUserNameAttribute(): string
    {
        return $this->headUser?->name ?? 'No Head Assigned';
    }

    /**
     * Get the full URL for the head image.
     */
    public function getHeadImageUrlAttribute(): ?string
    {
        return $this->head_image ? asset('storage/' . $this->head_image) : null;
    }

    /**
     * Get the documents in this department.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the members in this department.
     */
    public function members()
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'departments';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Departments';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-office';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    /**
     * Scope to get only active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
