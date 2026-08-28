<?php

namespace App\Services\Identity;

use App\Enums\Roles;
use App\Models\Member;
use App\Models\User;
use App\Services\PhoneFormattingService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ProvisionStudentUser
{
    public static bool $enabled = true;

    public function sync(Member $member): ?User
    {
        if (! self::$enabled) {
            return null;
        }

        $phone = trim((string) $member->phone);
        if ($phone === '') {
            return null;
        }

        if (! $member->hasActiveEnrollment()) {
            return $member->portalUser;
        }

        $user = User::query()->where('member_id', $member->id)->first()
            ?? User::query()->where('phone', $phone)->first();

        if ($user && $user->member_id && (int) $user->member_id !== (int) $member->id) {
            return $user;
        }

        if (! $user) {
            $tempPassword = PhoneFormattingService::nationalDigits($phone) ?: Str::password(16);

            $user = User::query()->create([
                'name' => $member->full_name,
                'email' => $member->email ?: 'student-'.$member->id.'@portal.local',
                'phone' => $phone,
                'password' => $tempPassword,
                'is_active' => true,
                'temp_password_changed' => false,
                'language_preference' => 'en',
                'member_id' => $member->id,
            ]);
        } else {
            $user->fill([
                'member_id' => $member->id,
                'phone' => $phone,
                'name' => $user->name ?: $member->full_name,
            ])->save();
        }

        $role = Role::findOrCreate(Roles::STUDENT, 'web');
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }
}
