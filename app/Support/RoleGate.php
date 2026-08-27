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
}
