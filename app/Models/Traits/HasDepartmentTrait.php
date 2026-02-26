<?php

namespace App\Models\Traits;

use App\Models\Scopes\HasDepartmentScope;
use Illuminate\Database\Eloquent\Model;

trait HasDepartmentTrait
{
    /**
     * Boot the trait
     */
    protected static function bootHasDepartmentTrait(): void
    {
        static::addGlobalScope(new HasDepartmentScope);
    }

    /**
     * Get the model without department scope
     */
    public static function withoutDepartmentScope()
    {
        return (new static)->withoutGlobalScope(HasDepartmentScope::class);
    }

    /**
     * Get the model with all departments (alias for withoutDepartmentScope)
     */
    public static function withAllDepartments()
    {
        return static::withoutDepartmentScope();
    }

    /**
     * Check if the current user can access this model
     */
    public function canCurrentUserAccess(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Superadmin and Admin can access all
        if ($user->hasRole(['superadmin', 'admin'])) {
            return true;
        }

        // HR Head can access all members and groups
        if ($user->hasRole('hr_head') && in_array($this->getTable(), ['members', 'groups'])) {
            return true;
        }

        // Check department access
        if ($user->department_id && $this->department_id) {
            return $user->department_id === $this->department_id;
        }

        return false;
    }

    /**
     * Scope to get records accessible by current user
     */
    public function scopeAccessibleByCurrentUser($query)
    {
        if (!auth()->check()) {
            return $query->whereRaw('1 = 0'); // No records if not authenticated
        }

        $user = auth()->user();

        // Superadmin and Admin can see all
        if ($user->hasRole(['superadmin', 'admin'])) {
            return $query;
        }

        // HR Head can see all members and groups
        if ($user->hasRole('hr_head') && in_array($this->getTable(), ['members', 'groups'])) {
            return $query;
        }

        // Department scope for other roles
        if ($user->department_id) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->whereRaw('1 = 0'); // No access if no department
    }
}
