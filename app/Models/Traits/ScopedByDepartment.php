<?php

namespace App\Models\Traits;

use App\Models\Scopes\DepartmentScope;
use Illuminate\Database\Eloquent\Model;

trait ScopedByDepartment
{
    /**
     * Boot the trait and apply the department scope.
     */
    protected static function bootScopedByDepartment(): void
    {
        static::addGlobalScope(new DepartmentScope);
    }

    /**
     * Get the model without department scope.
     */
    public static function withoutDepartmentScope()
    {
        return (new static)->withoutGlobalScope(DepartmentScope::class);
    }

    /**
     * Get the model with all departments (alias for withoutDepartmentScope).
     */
    public static function withAllDepartments()
    {
        return static::withoutDepartmentScope();
    }

    /**
     * Scope to get records accessible by current user.
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

        // Apply department scope for other users
        if ($user->department_id) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->whereRaw('1 = 0'); // No access if no department
    }

    /**
     * Check if the current user can access this model.
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

        // Check department access
        if ($user->department_id && $this->department_id) {
            return $user->department_id === $this->department_id;
        }

        return false;
    }

    /**
     * Get the department name for this model.
     */
    public function getDepartmentName(): ?string
    {
        if (!$this->department_id) {
            return null;
        }

        $department = \App\Models\Department::find($this->department_id);
        return $department ? $department->name_en : null;
    }

    /**
     * Get the department name in Amharic.
     */
    public function getDepartmentNameAmharic(): ?string
    {
        if (!$this->department_id) {
            return null;
        }

        $department = \App\Models\Department::find($this->department_id);
        return $department ? $department->name_am : null;
    }

    /**
     * Scope to get records by specific department.
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to get records by department name.
     */
    public function scopeByDepartmentName($query, $departmentName)
    {
        return $query->whereHas('department', function ($query) use ($departmentName) {
            $query->where('name_en', $departmentName);
        });
    }
}
