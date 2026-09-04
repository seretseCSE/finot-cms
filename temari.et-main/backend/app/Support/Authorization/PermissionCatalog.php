<?php

namespace App\Support\Authorization;

use Spatie\Permission\Models\Role;

/**
 * Read-only, per-request memo of the role → permission catalog.
 *
 * Spatie tables define WHAT each role can do (the catalog). WHERE a user holds a
 * role lives exclusively in `memberships` — users are never assigned Spatie roles
 * directly, and `model_has_roles` / `model_has_permissions` stay empty. Effective
 * authority is always computed as: membership roles applicable to a scope ×
 * this catalog (see User::allowedTo()).
 */
final class PermissionCatalog
{
    /** @var array<string, list<string>>|null */
    private static ?array $map = null;

    /**
     * Full catalog: role name → permission names.
     *
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return self::$map ??= Role::with('permissions')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [
                $role->name => $role->permissions->pluck('name')->values()->all(),
            ])
            ->all();
    }

    /**
     * Union of permission names granted by the given roles.
     *
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    public static function permissionsForRoles(array $roleNames): array
    {
        $map = self::map();
        $permissions = [];

        foreach ($roleNames as $name) {
            foreach ($map[$name] ?? [] as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }

    /**
     * Subset of the catalog for the given roles (role name → permissions).
     *
     * @param  list<string>  $roleNames
     * @return array<string, list<string>>
     */
    public static function mapForRoles(array $roleNames): array
    {
        return array_intersect_key(self::map(), array_flip($roleNames));
    }

    /** Reset the memo (tests / after re-seeding the catalog). */
    public static function flush(): void
    {
        self::$map = null;
    }
}
