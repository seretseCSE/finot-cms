<?php

namespace App\Models;

use App\Models\Traits\HasUserSessions;
use App\Presenters\UserPresenter;
use App\Services\AccountLockService;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUserSessions;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'password_history',
        'is_active',
        'is_locked',
        'temp_password_changed',
        'failed_login_attempts',
        'locked_until',
        'lock_reason',
        'locked_by',
        'locked_at',
        'department_id',
        'member_id',
        'parent_id',
        'language_preference',
        'tour_version',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'temp_password_changed' => 'boolean',
            'password_history' => 'array',
            'language_preference' => 'string',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Get the display name attribute.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get the Ethiopian join date attribute.
     */
    public function getEthiopianJoinDateAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y') : '';
    }

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the department scope for query scoping.
     */
    public function getDepartmentScope(): ?int
    {
        return $this->department_id;
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who locked this account.
     */
    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get the audit logs for the user.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the preferred locale.
     */
    public function getPreferredLocale(): string
    {
        return $this->language_preference ?? 'en';
    }

    /**
     * Add current password hash to history.
     */
    public function addToPasswordHistory(string $passwordHash, int $maxHistoryCount = null): void
    {
        $history = $this->password_history ?? [];

        // Add current password to the beginning of history
        array_unshift($history, $passwordHash);

        $maxHistoryCount = $maxHistoryCount ?? config('finot.password_history_count', 3);

        // Keep only the specified number of most recent passwords
        $this->password_history = array_slice($history, 0, $maxHistoryCount);

        $this->save();
    }

    /**
     * Get presenter instance for UI operations
     */
    public function present(): UserPresenter
    {
        return new UserPresenter($this);
    }

    /**
     * Check if the user is active.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->isActive() && (
            $this->hasAnyRole(\App\Enums\Roles::STAFF)
            || $this->hasRole(\App\Enums\Roles::STUDENT)
            || $this->hasRole(\App\Enums\Roles::PARENT)
        );
    }

    public function isStudentOnly(): bool
    {
        return $this->hasRole(\App\Enums\Roles::STUDENT) && ! $this->isStaff();
    }

    public function postLoginUrl(): string
    {
        if (! $this->temp_password_changed) {
            return route('change-initial-password');
        }

        return url('/admin');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function parentRecord()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function isParentOnly(): bool
    {
        return $this->hasRole(\App\Enums\Roles::PARENT) && ! $this->isStaff();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(\App\Enums\Roles::STAFF);
    }

    /**
     * Get the phone number for authentication.
     */
    public function username(): string
    {
        return $this->phone;
    }

    /**
     * Get the email for authentication fallback.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /**
     * Get the email field name for authentication compatibility.
     */
    public function getEmailName(): string
    {
        return 'phone';
    }

    /**
     * Find the user instance for the given phone number.
     */
    public static function findForPhone(string $phone): ?User
    {
        return static::where('phone', $phone)->first();
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedAttempts(): void
    {
        AccountLockService::resetFailedAttempts($this);
    }

    /**
     * Increment failed login attempts and lock if necessary.
     */
    public function incrementFailedAttempts(): void
    {
        AccountLockService::incrementFailedAttempts($this);
    }

    /**
     * Add current password to history and update password
     */
    public function updatePassword(string $newPassword, int $maxHistoryCount = null): void
    {
        $currentPasswordHash = $this->password;

        // Get current password history
        $history = $this->password_history ?? [];

        // Add current password to the beginning of history
        array_unshift($history, $currentPasswordHash);

        $maxHistoryCount = $maxHistoryCount ?? config('finot.password_history_count', 3);

        // Keep only the last N passwords
        $history = array_slice($history, 0, $maxHistoryCount);

        // Update password and history
        $this->update([
            'password' => $newPassword,
            'password_history' => $history,
            'temp_password_changed' => true,
        ]);

        $this->persistAuthPasswordHashInSession();
    }

    /**
     * Keep the current browser session valid after a password change.
     * Without this, AuthenticateSession logs the user out because the
     * hash stored in the session no longer matches.
     */
    public function persistAuthPasswordHashInSession(): void
    {
        if (! app()->bound('session')) {
            return;
        }

        session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $this->getAuthPassword()
        );
    }

    /**
     * Check if password has been used before
     */
    public function isPasswordInHistory(string $password, int $maxHistoryCount = null): bool
    {
        $history = $this->password_history ?? [];

        if (empty($history)) {
            return false;
        }

        $maxHistoryCount = $maxHistoryCount ?? config('finot.password_history_count', 3);

        // Check against last N passwords
        $recentHistory = array_slice($history, 0, $maxHistoryCount);

        foreach ($recentHistory as $oldPasswordHash) {
            if (Hash::check($password, $oldPasswordHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get password history as array
     */
    public function getPasswordHistory(int $maxCount = null): array
    {
        $history = $this->password_history ?? [];

        $maxCount = $maxCount ?? config('finot.password_history_count', 3);

        return array_slice($history, 0, $maxCount);
    }

    /**
     * Lock user account.
     */
    public function lockAccount(string $reason, string $duration, ?int $lockedBy = null): bool
    {
        return AccountLockService::lockAccount($this, $reason, $duration, $lockedBy);
    }

    /**
     * Unlock user account.
     */
    public function unlockAccount(?int $unlockedBy = null): bool
    {
        return AccountLockService::unlockAccount($this, $unlockedBy);
    }

    /**
     * Check if account is currently locked.
     */
    public function isCurrentlyLocked(): bool
    {
        return AccountLockService::isCurrentlyLocked($this);
    }

    /**
     * Get lock status badge data.
     */
    public function getLockStatusBadge(): array
    {
        return $this->present()->lockStatusBadge();
    }

    /**
     * Get lockout message
     */
    public function getLockoutMessage(): string
    {
        return AccountLockService::getLockoutMessage($this);
    }

    /**
     * Get remaining lockout time in minutes
     */
    public function getRemainingLockoutMinutes(): int
    {
        return AccountLockService::getRemainingLockoutMinutes($this);
    }
}
