<?php

namespace App\Services\Identity;

use App\Contracts\SmsGateway;
use App\Enums\Roles;
use App\Models\ParentModel;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\PhoneFormattingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class ProvisionParentUser
{
    public static bool $enabled = true;

    public function __construct(private SmsGateway $sms)
    {
    }

    public function sync(ParentModel $parent): ?User
    {
        if (! self::$enabled) {
            return null;
        }

        $phone = trim((string) $parent->phone);
        if ($phone === '' || ! $parent->is_active) {
            return $parent->portalUser;
        }

        $hasEnrolledChild = StudentEnrollment::query()
            ->active()
            ->whereIn('member_id', $parent->parentGuardians()->pluck('member_id'))
            ->exists();

        if (! $hasEnrolledChild) {
            return $parent->portalUser;
        }

        $user = User::query()->where('parent_id', $parent->id)->first()
            ?? User::query()->where('phone', $phone)->whereNull('member_id')->first();

        if ($user && $user->member_id) {
            // Phone already used by a student/staff member account — do not hijack
            return $parent->portalUser;
        }

        if ($user && $user->parent_id && (int) $user->parent_id !== (int) $parent->id) {
            return $user;
        }

        $created = false;
        $plainPassword = null;

        if (! $user) {
            $plainPassword = PhoneFormattingService::nationalDigits($phone) ?: Str::password(10, symbols: false);
            $created = true;

            $user = User::query()->create([
                'name' => $parent->full_name,
                'email' => 'parent-'.$parent->id.'@portal.local',
                'phone' => $phone,
                'password' => $plainPassword,
                'is_active' => true,
                'temp_password_changed' => false,
                'language_preference' => 'am',
                'parent_id' => $parent->id,
            ]);
        } else {
            $user->fill([
                'parent_id' => $parent->id,
                'phone' => $phone,
                'name' => $user->name ?: $parent->full_name,
            ])->save();
        }

        $role = Role::findOrCreate(Roles::PARENT, 'web');
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
            "ሰላም {$user->name}፣ እንኳን ወደ ፊኖተ ጽድቅ የወላጅ ፖርታል በደህና መጡ።",
            "ስልክ፦ {$user->phone}",
            "የይለፍ ቃል፦ {$plainPassword}",
            'የልጆችዎን ውጤት፣ ማስታወቂያ እና የቤት ስራ ማየት ይችላሉ።',
            'እባክዎ ከገቡ በኋላ የይለፍ ቃልዎን ይቀይሩ።',
        ]);

        try {
            $this->sms->send((string) $user->phone, $message);
        } catch (Throwable $e) {
            Log::warning('Parent welcome SMS failed', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
