<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        if (! $user->hasContextPermission('users.view')) {
            return false;
        }

        // Visibility mirrors the list scope (scopeManageableBy / sharesScopeWith):
        // if a user shows up in the actor's scope, the actor may open them. The
        // stricter hierarchy check only gates mutating actions, not viewing.
        return $user->id === $target->id
            || $user->isPlatformUser()
            || $user->sharesScopeWith($target);
    }

    public function export(User $user): bool
    {
        return $user->hasContextPermission('users.export');
    }

    /**
     * Editing a user's profile information is reserved for Temari.et platform staff.
     */
    public function create(User $user): bool
    {
        return $user->hasPlatformPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPlatformPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPlatformPermission('users.delete')
            && $user->id !== $target->id;
    }

    /**
     * Taking an account back out of the bin is the inverse of putting it there,
     * so it rides the same permission: whoever can see trashed rows in the list
     * (gated on `users.delete` in UserController@baseQuery) can restore them.
     */
    public function restore(User $user, User $target): bool
    {
        return $user->hasPlatformPermission('users.delete');
    }

    /**
     * Global account status (activate / deactivate / ban / restore) is platform-only.
     */
    public function setStatus(User $user, User $target): bool
    {
        return $user->hasPlatformPermission('users.status')
            && $user->id !== $target->id;
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->hasPlatformPermission('users.reset_password');
    }

    public function impersonate(User $user): bool
    {
        return $user->hasPlatformPermission('users.impersonate');
    }
}
