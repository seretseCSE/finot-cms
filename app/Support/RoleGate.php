<?php

namespace App\Support;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Single entry point for page/UI authorization.
 *
 * Resources: BaseResource + model getPermissionName().
 * Pages and one-off UI: RoleGate::can() / RoleGate::isAny().
 *
 * Multi-role users pick an active dashboard via session('active_role').
 * RoleGate::is() / isAny() match that active role so widgets and nav switch.
 * RoleGate::can() still uses the union of Spatie permissions from every role.
 */
class RoleGate
{
    public const SESSION_KEY = 'active_role';

    /** Roles that may message every member, with advanced audience filters. */
    public const GLOBAL_BROADCAST_ROLES = [
        'superadmin',
        'admin',
        'hr_head',
        'internal_relations_head',
        'education_head',
    ];

    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function check(): bool
    {
        return static::user() !== null;
    }

    public static function is(string|array $roles): bool
    {
        $user = static::user();
        if (! $user) {
            return false;
        }

        $active = static::activeRole($user);
        if ($active) {
            return in_array($active, (array) $roles, true);
        }

        return $user->hasRole($roles);
    }

    public static function isAny(array $roles): bool
    {
        return static::is($roles);
    }

    public static function can(?string $permission): bool
    {
        if (! $permission) {
            return false;
        }

        $user = static::user();

        if (! $user) {
            return false;
        }

        return $user->can($permission);
    }

    public static function canBroadcastGlobal(): bool
    {
        $user = static::user();

        if (! $user) {
            return false;
        }

        return $user->can('messages.broadcast_global')
            || $user->hasAnyRole(self::GLOBAL_BROADCAST_ROLES);
    }

    public static function canApproveBookings(): bool
    {
        return static::isAny(['superadmin', 'admin']);
    }

    public static function activeRole(?User $user = null): ?string
    {
        $user ??= static::user();
        if (! $user) {
            return null;
        }

        $sessionRole = session(self::SESSION_KEY);
        if (is_string($sessionRole) && $sessionRole !== '' && $user->hasRole($sessionRole)) {
            return $sessionRole;
        }

        return static::defaultRole($user);
    }

    public static function defaultRole(User $user): ?string
    {
        $names = $user->getRoleNames()->all();
        if ($names === []) {
            return null;
        }

        foreach (Roles::STAFF as $role) {
            if (in_array($role, $names, true)) {
                return $role;
            }
        }

        return $names[0] ?? null;
    }

    public static function switchTo(string $role, ?User $user = null): bool
    {
        $user ??= static::user();
        if (! $user || ! $user->hasRole($role)) {
            return false;
        }

        session([self::SESSION_KEY => $role]);

        return true;
    }

    public static function rememberDefault(?User $user = null): void
    {
        $user ??= static::user();
        if (! $user) {
            return;
        }

        $current = session(self::SESSION_KEY);
        if (is_string($current) && $user->hasRole($current)) {
            return;
        }

        $default = static::defaultRole($user);
        if ($default) {
            session([self::SESSION_KEY => $default]);
        }
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    public static function switchableRoles(?User $user = null): array
    {
        $user ??= static::user();
        if (! $user) {
            return [];
        }

        return $user->roles
            ->map(fn ($role) => [
                'name' => (string) $role->name,
                'label' => (string) ($role->label ?: str_replace('_', ' ', ucwords((string) $role->name, '_'))),
            ])
            ->values()
            ->all();
    }
}
