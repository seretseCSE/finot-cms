<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Membership;
use Illuminate\Support\Collection;

/**
 * A school/branch "contact": the person holding a management role (principal,
 * school_admin, director). Name and phone come from the linked user account.
 */
class ContactResource
{
    /**
     * @return array{user_id: int|null, membership_id: int, name: string|null, phone: string|null, is_active: bool}
     */
    public static function fromMembership(Membership $membership): array
    {
        return [
            'user_id' => $membership->user_id,
            'membership_id' => $membership->id,
            'name' => $membership->user?->name,
            'phone' => $membership->user?->phone,
            'is_active' => (bool) $membership->is_active,
        ];
    }

    /**
     * Pick the contact holding the given role from a loaded membership collection.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return array{user_id: int|null, membership_id: int, name: string|null, phone: string|null, is_active: bool}|null
     */
    public static function fromMemberships(Collection $memberships, Role $role): ?array
    {
        $match = $memberships->first(
            fn (Membership $m): bool => $m->role === $role || $m->role?->value === $role->value,
        );

        return $match ? self::fromMembership($match) : null;
    }
}
