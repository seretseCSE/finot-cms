<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ParentProfile;
use App\Models\Student;

/**
 * Keeps the login account's avatar in step with the person's profile photo.
 *
 * The student/parent/employee photo endpoints call `sync()` — the freshly
 * uploaded profile photo IS the person's picture, so it always wins over
 * whatever the avatar held. Account-provisioning paths call `seed()` — a
 * reused account may already carry an avatar the user chose themselves, so
 * seeding only fills an EMPTY one.
 *
 * Both point `users.avatar_path` at the same R2 object as the profile's
 * `photo_path` (avatarUrl() signs it exactly like photo_url) — no copy, so a
 * photo replacement that deletes the old object can never leave the avatar
 * dangling as long as it re-syncs, which the photo endpoints always do.
 */
class ProfilePhotoSync
{
    public static function sync(Student|Employee|ParentProfile $profile): void
    {
        self::apply($profile, overwrite: true);
    }

    public static function seed(Student|Employee|ParentProfile $profile): void
    {
        self::apply($profile, overwrite: false);
    }

    private static function apply(Student|Employee|ParentProfile $profile, bool $overwrite): void
    {
        $user = $profile->user()->first();

        if ($user === null || $profile->photo_path === null) {
            return;
        }

        if (! $overwrite && $user->avatar_path !== null) {
            return;
        }

        if ($user->avatar_path === $profile->photo_path) {
            return;
        }

        $user->forceFill(['avatar_path' => $profile->photo_path])->save();
    }
}
