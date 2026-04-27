<?php

namespace App\Services\UI;

use App\Models\User;
use App\Services\Auth\AccountLockoutService;

class UserBadgeService
{
    /**
     * Get lock status badge data for Filament UI display.
     *
     * @return array<string, mixed>
     */
    public function getLockStatusBadge(User $user): array
    {
        if (! app(AccountLockoutService::class)->isCurrentlyLocked($user)) {
            return [
                'status' => 'Active',
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        }

        if ($user->locked_until === null) {
            return [
                'status' => 'Permanently Locked',
                'color' => 'danger',
                'icon' => 'heroicon-o-lock-closed',
            ];
        }

        return [
            'status' => 'Locked',
            'color' => 'danger',
            'icon' => 'heroicon-o-lock-closed',
            'until' => $user->locked_until->format('M j, Y H:i'),
        ];
    }

    /**
     * Get a human-readable lock status message.
     */
    public function getLockStatusMessage(User $user): string
    {
        if ($user->is_locked) {
            return 'Account disabled. Please contact administrator.';
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return app(AccountLockoutService::class)->getLockoutMessage($user);
        }

        return '';
    }
}
