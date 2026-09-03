<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserCreationService
{
    /**
     * Find a registered member by phone (accepts 9-digit form state or +251… stored format).
     */
    public function findMemberByPhone(?string $phone): ?Member
    {
        $normalized = PhoneFormattingService::normalizeForAuth($phone);

        if ($normalized === null) {
            return null;
        }

        return Member::query()->where('phone', $normalized)->first();
    }

    /**
     * User form attributes that can be copied from a Member.
     *
     * @return array{name?: string, email?: string}
     */
    public function attributesFromMember(Member $member): array
    {
        $attributes = [
            'name' => $member->full_name,
        ];

        $email = trim((string) $member->email);
        if ($email !== '') {
            $attributes['email'] = $email;
        }

        return $attributes;
    }

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
