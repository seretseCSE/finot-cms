<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryItemPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('inventory_items.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory_items.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('inventory_items.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory_items.update');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * An item can only be deleted if it has no stock movements.
     */
    public function delete(User $user, InventoryItem $item): bool
    {
        if (! $user->can('inventory_items.delete')) {
            return false;
        }

        return $item->canBeDeleted();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory_items.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory_items.force_delete');
    }
}
