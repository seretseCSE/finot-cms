<?php

namespace App\Policies;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TourPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tours.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tour $tour): bool
    {
        return $user->can('tours.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tours.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tour $tour): bool
    {
        return $user->can('tours.update');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * A tour can only be deleted if it has no passengers.
     */
    public function delete(User $user, Tour $tour): bool
    {
        if (! $user->can('tours.delete')) {
            return false;
        }

        return $tour->canBeDeleted();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Tour $tour): bool
    {
        return $user->can('tours.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tour $tour): bool
    {
        return $user->can('tours.force_delete');
    }
}
