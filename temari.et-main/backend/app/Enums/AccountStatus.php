<?php

namespace App\Enums;

/**
 * Global account status for a user. This governs platform-wide access and is
 * managed ONLY by Temari.et platform staff. It is deliberately separate from
 * branch/school scope access, which lives on `memberships.is_active`.
 *
 *  - active:   normal access
 *  - inactive: temporarily disabled by an admin (reversible)
 *  - banned:   disabled for policy/abuse reasons (reversible by admin only)
 *
 * Both `inactive` and `banned` revoke access to the entire platform.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Banned => 'Banned',
        };
    }

    /**
     * Whether this status permits access to the platform.
     */
    public function grantsAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * The response code surfaced to the client when access is denied, so the
     * frontend can show the correct message / screen.
     */
    public function deniedCode(): ?string
    {
        return match ($this) {
            self::Active => null,
            self::Inactive => 'account_inactive',
            self::Banned => 'account_banned',
        };
    }
}
