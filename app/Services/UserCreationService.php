<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserCreationService
{
    /**
     * Process data before creating user.
     *
     * @param array $data The form data
     * @return array The modified data with roles extracted
     */
    public function processBeforeCreate(array $data): array
    {
        // Ensure temp_password_changed is false so user is forced to change password
        $data['temp_password_changed'] = false;

        return $data;
    }

    /**
     * Sync roles to user after creation.
     *
     * @param User $user The user instance
     * @param array $roles The roles to sync
     * @return void
     */
    public function syncRoles(User $user, array $roles): void
    {
        if (!empty($roles)) {
            $user->syncRoles($roles);
        }
    }

    /**
     * Log user creation to audit trail.
     *
     * @param User $user The created user
     * @return void
     */
    public function logUserCreation(User $user): void
    {
        Log::channel('audit')->info('User Created', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'created_by' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
