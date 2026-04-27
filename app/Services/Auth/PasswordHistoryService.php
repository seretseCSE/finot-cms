<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordHistoryService
{
    /**
     * Add a password hash to the user's history.
     */
    public function addToPasswordHistory(User $user, string $passwordHash, ?int $maxHistoryCount = null): void
    {
        $history = $user->password_history ?? [];

        array_unshift($history, $passwordHash);

        $maxHistoryCount = $maxHistoryCount ?? config('finot.password_history_count', 3);

        $user->password_history = array_slice($history, 0, $maxHistoryCount);
        $user->save();
    }

    /**
     * Update the user's password and append the old one to history.
     */
    public function updatePassword(User $user, string $newPassword, ?int $maxHistoryCount = null): void
    {
        $currentPasswordHash = $user->password;

        $history = $user->password_history ?? [];
        array_unshift($history, $currentPasswordHash);

        $maxHistoryCount = $maxHistoryCount ?? config('finot.password_history_count', 3);

        $user->update([
            'password' => $newPassword,
            'password_history' => array_slice($history, 0, $maxHistoryCount),
            'temp_password_changed' => true,
        ]);
    }

    /**
     * Check if a password has been used before.
     */
    public function isPasswordInHistory(User $user, string $password, ?int $maxHistoryCount = null): bool
    {
        $history = $user->password_history ?? [];

        if (empty($history)) {
            return false;
        }

        $maxHistoryCount = $maxHistoryCount ?? config('finot.password_history_count', 3);
        $recentHistory = array_slice($history, 0, $maxHistoryCount);

        foreach ($recentHistory as $oldPasswordHash) {
            if (Hash::check($password, $oldPasswordHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the user's password history.
     */
    public function getPasswordHistory(User $user, ?int $maxCount = null): array
    {
        $history = $user->password_history ?? [];

        $maxCount = $maxCount ?? config('finot.password_history_count', 3);

        return array_slice($history, 0, $maxCount);
    }
}
