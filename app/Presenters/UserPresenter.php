<?php

namespace App\Presenters;

use App\Models\User;
use App\Services\AccountLockService;

class UserPresenter
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get display name
     */
    public function displayName(): string
    {
        return $this->user->name;
    }

    /**
     * Get Ethiopian join date
     */
    public function ethiopianJoinDate(): string
    {
        return $this->user->created_at ? $this->user->created_at->format('M d, Y') : '';
    }

    /**
     * Get lock status badge data
     */
    public function lockStatusBadge(): array
    {
        if (!AccountLockService::isCurrentlyLocked($this->user)) {
            return [
                'status' => 'Active',
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        }

        if ($this->user->locked_until === null) {
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
            'until' => $this->user->locked_until->format('M j, Y H:i'),
        ];
    }

    /**
     * Get lock status message
     */
    public function lockStatusMessage(): string
    {
        return AccountLockService::getLockStatusMessage($this->user);
    }

    /**
     * Get remaining lockout time in minutes
     */
    public function remainingLockoutMinutes(): int
    {
        return AccountLockService::getRemainingLockoutMinutes($this->user);
    }

    /**
     * Get formatted lockout message
     */
    public function lockoutMessage(): string
    {
        return AccountLockService::getLockoutMessage($this->user);
    }

    /**
     * Get user status badge data
     */
    public function statusBadge(): array
    {
        if (!$this->user->is_active) {
            return [
                'status' => 'Inactive',
                'color' => 'gray',
                'icon' => 'heroicon-o-x-circle',
            ];
        }

        if (!$this->user->temp_password_changed) {
            return [
                'status' => 'Password Change Required',
                'color' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        if (AccountLockService::isCurrentlyLocked($this->user)) {
            return $this->lockStatusBadge();
        }

        return [
            'status' => 'Active',
            'color' => 'success',
            'icon' => 'heroicon-o-check-circle',
        ];
    }

    /**
     * Get role badge data
     */
    public function roleBadge(): array
    {
        $roles = $this->user->getRoleNames();

        if ($roles->isEmpty()) {
            return [
                'status' => 'No Role',
                'color' => 'gray',
                'icon' => 'heroicon-o-user',
            ];
        }

        $primaryRole = $roles->first();

        return match ($primaryRole) {
            'superadmin' => [
                'status' => 'Super Admin',
                'color' => 'purple',
                'icon' => 'heroicon-o-shield-check',
            ],
            'admin' => [
                'status' => 'Admin',
                'color' => 'blue',
                'icon' => 'heroicon-o-shield-check',
            ],
            default => [
                'status' => ucfirst($primaryRole),
                'color' => 'green',
                'icon' => 'heroicon-o-user',
            ],
        };
    }

    /**
     * Get department name
     */
    public function departmentName(): ?string
    {
        return $this->user->department?->name_en ?? null;
    }

    /**
     * Get department name in Amharic
     */
    public function departmentNameAmharic(): ?string
    {
        return $this->user->department?->name_am ?? null;
    }

    /**
     * Get preferred locale
     */
    public function preferredLocale(): string
    {
        return $this->user->language_preference ?? 'en';
    }

    /**
     * Get last login formatted
     */
    public function lastLoginFormatted(): string
    {
        if (!$this->user->last_login_at) {
            return 'Never';
        }

        return $this->user->last_login_at->format('M j, Y H:i');
    }

    /**
     * Get full user summary for UI display
     */
    public function summary(): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->displayName(),
            'phone' => $this->user->phone,
            'email' => $this->user->email,
            'status' => $this->statusBadge(),
            'role' => $this->roleBadge(),
            'department' => $this->departmentName(),
            'lock_status' => $this->lockStatusBadge(),
            'last_login' => $this->lastLoginFormatted(),
            'created_at' => $this->ethiopianJoinDate(),
        ];
    }
}
