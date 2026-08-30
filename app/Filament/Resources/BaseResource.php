<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\EnsuresTableCreateAction;
use App\Models\Department;
use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class BaseResource extends Resource
{
    use EnsuresTableCreateAction;

    /**
     * Authenticated user, or null when the request is a guest.
     */
    protected static function authUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Superadmin always passes; otherwise require the model's permission for $action.
     */
    protected static function userHasPermission(string $action): bool
    {
        $user = static::authUser();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (! method_exists(static::getModel(), 'getPermissionName')) {
            return false;
        }

        return $user->can(static::getModel()::getPermissionName($action));
    }

    /**
     * Permission check plus department scoping for a single record.
     */
    protected static function userCanAccessRecord(Model $record, string $action): bool
    {
        if (! static::userHasPermission($action)) {
            return false;
        }

        if (static::authUser()?->hasRole('superadmin')) {
            return true;
        }

        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }

        return true;
    }

    /**
     * Determine if the current user can view any resources of this type.
     */
    public static function canViewAny(): bool
    {
        return static::userHasPermission('view');
    }

    /**
     * Determine if the current user can create resources.
     */
    public static function canCreate(): bool
    {
        return static::userHasPermission('create');
    }

    /**
     * Determine if the current user can edit the given resource.
     */
    public static function canEdit(Model $record): bool
    {
        return static::userCanAccessRecord($record, 'update');
    }

    /**
     * Determine if the current user can delete the given resource.
     */
    public static function canDelete(Model $record): bool
    {
        return static::userCanAccessRecord($record, 'delete');
    }

    /**
     * Determine if the current user can view the given resource.
     */
    public static function canView(Model $record): bool
    {
        return static::userCanAccessRecord($record, 'view');
    }

    /**
     * Determine if the current user can restore the given resource.
     */
    public static function canRestore(Model $record): bool
    {
        return static::userCanAccessRecord($record, 'restore');
    }

    /**
     * Determine if the current user can force delete the given resource.
     */
    public static function canForceDelete(Model $record): bool
    {
        return static::userCanAccessRecord($record, 'force_delete');
    }

    /**
     * Determine if the current user can bulk-delete resources.
     */
    public static function canDeleteAny(): bool
    {
        return static::userHasPermission('delete_any');
    }

    /**
     * Determine if the current user can force delete any resources (bulk).
     */
    public static function canForceDeleteAny(): bool
    {
        return static::userHasPermission('force_delete_any');
    }

    /**
     * Determine if the current user can reorder resources.
     */
    public static function canReorder(): bool
    {
        return static::userHasPermission('reorder');
    }

    /**
     * Determine if the current user can replicate the given resource.
     */
    public static function canReplicate(Model $record): bool
    {
        return static::userCanAccessRecord($record, 'replicate');
    }

    /**
     * Determine if the current user can restore any resources (bulk).
     */
    public static function canRestoreAny(): bool
    {
        return static::userHasPermission('restore_any');
    }

    /**
     * Get the Eloquent query for the resource with proper scoping.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        $traits = class_uses_recursive(static::getModel());
        if (isset($traits[\App\Models\Traits\ScopedByDepartment::class])) {
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
        $user = static::authUser();

        if (! $user) {
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
        $user = static::authUser();

        if (! $user) {
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
