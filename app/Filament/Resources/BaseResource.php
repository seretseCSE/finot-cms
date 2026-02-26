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

        // Check specific permission
        $permission = static::getModel()::getPermissionName('view');
        return $user->can($permission);
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

        // Check specific permission
        $permission = static::getModel()::getPermissionName('create');
        return $user->can($permission);
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

        // Check specific permission
        $permission = static::getModel()::getPermissionName('update');

        // Check permission and department access
        if (!$user->can($permission)) {
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

        // Check specific permission
        $permission = static::getModel()::getPermissionName('delete');

        // Check permission and department access
        if (!$user->can($permission)) {
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

        // Check specific permission
        $permission = static::getModel()::getPermissionName('view');

        // Check permission and department access
        if (!$user->can($permission)) {
            return false;
        }

        // Check department access if model has department trait
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Get the Eloquent query for the resource with proper scoping.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        // Apply department scope if model has the trait
        if (in_array('App\Models\Traits\HasDepartmentTrait', class_uses(static::getModel()))) {
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
}

