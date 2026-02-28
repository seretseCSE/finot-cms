<?php

namespace App\Models;

use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, ScopedByDepartment, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_am',
        'code',
        'description',
        'icon',
        'head_user_id',
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
