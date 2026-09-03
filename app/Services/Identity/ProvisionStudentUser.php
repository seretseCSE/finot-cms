<?php

namespace App\Services\Identity;

use App\Contracts\SmsGateway;
use App\Enums\Roles;
use App\Models\Member;
use App\Models\User;
use App\Services\PhoneFormattingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class ProvisionStudentUser
{
    public static bool $enabled = true;

    public function __construct(private SmsGateway $sms)
    {
    }

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

        $created = false;
        $plainPassword = null;

        if (! $user) {
            $plainPassword = PhoneFormattingService::nationalDigits($phone) ?: Str::password(10, symbols: false);
            $created = true;

            $user = User::query()->create([
                'name' => $member->full_name,
                'email' => $member->email ?: 'student-'.$member->id.'@portal.local',
                'phone' => $phone,
                'password' => $plainPassword,
                'is_active' => true,
                'temp_password_changed' => false,
                'language_preference' => 'am',
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

        if ($created && $plainPassword) {
            $this->sendWelcomeSms($user, $plainPassword);
        }

        return $user->fresh();
    }

    protected function sendWelcomeSms(User $user, string $plainPassword): void
    {
        $message = implode("\n", [
            "ሰላም {$user->name}፣ እንኳን ወደ ፊኖተ ጽድቅ በደህና መጡ።",
            "ስም፦ {$user->name}",
            "ስልክ፦ {$user->phone}",
            "የይለፍ ቃል፦ {$plainPassword}",
            'እባክዎ ከገቡ በኋላ የይለፍ ቃልዎን ይቀይሩ።',
        ]);

        try {
            $this->sms->send((string) $user->phone, $message);
        } catch (Throwable $e) {
            Log::warning('Student welcome SMS failed', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
