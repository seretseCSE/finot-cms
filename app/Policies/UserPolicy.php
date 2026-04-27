<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Prevents self-lockout and prevents non-superadmins from editing superadmins.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        // Prevent editing own account through this resource to avoid self-lockout
        if ($model->id === $user->id) {
            return false;
        }

        // Admin cannot edit superadmin
        if ($model->hasRole('superadmin') && ! $user->hasRole('superadmin')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Only superadmin can delete users. Cannot delete yourself.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->can('users.delete')) {
            return false;
        }

        // Only superadmin can delete users
        if (! $user->hasRole('superadmin')) {
            return false;
        }

        // Cannot delete yourself
        if ($model->id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('superadmin');
    }
}
