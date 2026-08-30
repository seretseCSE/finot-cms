<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Single entry point for page/UI authorization.
 *
 * Resources: BaseResource + model getPermissionName().
 * Pages and one-off UI: RoleGate::can() / RoleGate::isAny().
 */
class RoleGate
{
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
        return (bool) static::user()?->hasRole($roles);
    }

    public static function isAny(array $roles): bool
    {
        return (bool) static::user()?->hasAnyRole($roles);
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
}
