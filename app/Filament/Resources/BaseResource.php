<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class BaseResource extends Resource
{
    /**
     * Determine if the current user can view any resources of this type.
     */
    public static function canViewAny(): bool
    {
        $user = Auth::user();

        // Superadmin can view everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getModel(), 'getPermissionName')) {
            $permission = static::getModel()::getPermissionName('view');
            return $user->can($permission);
        }

        // Fallback to superadmin only for models without permission system
        return false;
    }

    /**
     * Determine if the current user can create resources.
     */
    public static function canCreate(): bool
    {
        $user = Auth::user();

        // Superadmin can create everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getModel(), 'getPermissionName')) {
            $permission = static::getModel()::getPermissionName('create');
            return $user->can($permission);
        }

        // Fallback to superadmin only for models without permission system
        return false;
    }

    /**
     * Determine if the current user can edit the given resource.
     */
    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        // Superadmin can edit everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getModel(), 'getPermissionName')) {
            $permission = static::getModel()::getPermissionName('update');

            // Check permission and department access
            if (!$user->can($permission)) {
                return false;
            }
        } else {
            // Fallback to superadmin only for models without permission system
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can delete the given resource.
     */
    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();

        // Superadmin can delete everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getModel(), 'getPermissionName')) {
            $permission = static::getModel()::getPermissionName('delete');

            // Check permission and department access
            if (!$user->can($permission)) {
                return false;
            }
        } else {
            // Fallback to superadmin only for models without permission system
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can view the given resource.
     */
    public static function canView(Model $record): bool
    {
        $user = Auth::user();

        // Superadmin can view everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getModel(), 'getPermissionName')) {
            $permission = static::getModel()::getPermissionName('view');

            // Check permission and department access
            if (! $user->can($permission)) {
                return false;
            }
        } else {
            // Fallback to superadmin only for models without permission system
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can restore the given resource.
     */
    public static function canRestore(Model $record): bool
    {
        $user = Auth::user();

        // Superadmin can restore everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission
        $permission = static::getModel()::getPermissionName('restore');

        // Check permission and department access
        if (! $user->can($permission)) {
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can force delete the given resource.
     */
    public static function canForceDelete(Model $record): bool
    {
        $user = Auth::user();

        // Superadmin can force delete everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission
        $permission = static::getModel()::getPermissionName('force_delete');

        // Check permission and department access
        if (! $user->can($permission)) {
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can bulk-delete resources.
     */
    public static function canDeleteAny(): bool
    {
        $user = Auth::user();

        // Superadmin can delete everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check bulk delete permission
        $permission = static::getModel()::getPermissionName('delete_any');

        return $user->can($permission);
    }

    /**
     * Determine if the current user can force delete any resources (bulk).
     */
    public static function canForceDeleteAny(): bool
    {
        $user = Auth::user();

        // Superadmin can force delete everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check bulk force delete permission
        $permission = static::getModel()::getPermissionName('force_delete_any');

        return $user->can($permission);
    }

    /**
     * Determine if the current user can reorder resources.
     */
    public static function canReorder(): bool
    {
        $user = Auth::user();

        // Superadmin can reorder everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check reorder permission
        $permission = static::getModel()::getPermissionName('reorder');

        return $user->can($permission);
    }

    /**
     * Determine if the current user can replicate the given resource.
     */
    public static function canReplicate(Model $record): bool
    {
        $user = Auth::user();

        // Superadmin can replicate everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission
        $permission = static::getModel()::getPermissionName('replicate');

        // Check permission and department access
        if (! $user->can($permission)) {
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can restore any resources (bulk).
     */
    public static function canRestoreAny(): bool
    {
        $user = Auth::user();

        // Superadmin can restore everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check bulk restore permission
        $permission = static::getModel()::getPermissionName('restore_any');

        return $user->can($permission);
    }

    /**
     * Get the Eloquent query for the resource with proper scoping.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        // Apply department scope if model has the trait
        $modelTraits = class_uses(static::getModel());
        if (in_array('App\Models\Traits\HasDepartmentTrait', $modelTraits) || in_array('App\Models\Traits\ScopedByDepartment', $modelTraits)) {
            return $query->accessibleByCurrentUser();
        }

        return $query;
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return static::getModel()::getNavigationLabel();
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return static::getModel()::getNavigationIcon();
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return static::getModel()::getNavigationGroup();
    }

    /**
     * Determine if the current user is a department head.
     */
    protected static function isDepartmentHead(?Model $record = null): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user is head of any department (or specific record's department)
        $query = Department::query()->where('head_user_id', $user->id);

        if ($record?->department_id) {
            $query->where('id', $record->department_id);
        }

        return $query->exists();
    }

    /**
     * Determine if the current user is a department secretary.
     */
    protected static function isDepartmentSecretary(?Model $record = null): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user is secretary of any department (or specific record's department)
        $query = Department::query()->where('secretary_user_id', $user->id);

        if ($record?->department_id) {
            $query->where('id', $record->department_id);
        }

        return $query->exists();
    }
}
