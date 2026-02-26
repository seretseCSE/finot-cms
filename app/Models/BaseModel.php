<?php

namespace App\Models;

use App\Models\Traits\HasEthiopianDates;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\GeneratesAutoId;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasEthiopianDates, HasAuditLog, GeneratesAutoId;

    /**
     * Boot the BaseModel with all traits.
     */
    protected static function boot(): void
    {
        parent::boot();
    }

    /**
     * Get the permission name for a given action.
     */
    public static function getPermissionName(string $action): string
    {
        $resourceName = static::getResourceName();
        return "{$resourceName}.{$action}";
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        // Default to table name, can be overridden in child classes
        return static::getTable();
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return ucfirst(str_replace('_', ' ', static::getTable()));
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return null;
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    /**
     * Check if the current user has permission for this model action.
     */
    public function currentUserCan(string $action): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();
        
        // Superadmin can do everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        $permission = static::getPermissionName($action);
        return $user->can($permission);
    }

    /**
     * Get all permissions for this model.
     */
    public static function getAllPermissions(): array
    {
        $resourceName = static::getResourceName();
        return [
            'view' => "{$resourceName}.view",
            'create' => "{$resourceName}.create",
            'update' => "{$resourceName}.update",
            'delete' => "{$resourceName}.delete",
        ];
    }

    /**
     * Scope to get records that current user can access.
     */
    public function scopeAccessibleByCurrentUser($query)
    {
        if (!auth()->check()) {
            return $query->whereRaw('1 = 0'); // No records if not authenticated
        }

        $user = auth()->user();

        // Superadmin can access all
        if ($user->hasRole('superadmin')) {
            return $query;
        }

        return $query->whereRaw('1 = 0'); // No access if no department
    }
}
