<?php

namespace App\Services\Notify;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;

/**
 * Sign-in device ledger behind `security.new_device`. The fingerprint is a
 * hash of the user-agent — deliberately coarse (no IP in the hash, no canvas
 * tricks): the goal is "someone signed in from a phone you've never used",
 * not ad-tech tracking. The FIRST device ever is silent — telling a user
 * about the sign-up they just performed is noise; every later first-seen
 * fingerprint notifies immediately (in-app + whitelisted SMS + email).
 */
class DeviceTracker
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function track(User $user, Request $request): void
    {
        $agent = (string) $request->userAgent();
        $fingerprint = hash('sha256', $agent);
        $isFirstDeviceEver = ! UserDevice::query()->where('user_id', $user->id)->exists();

        $device = UserDevice::query()->firstOrCreate(
            ['user_id' => $user->id, 'fingerprint' => $fingerprint],
            ['label' => self::label($agent), 'ip' => $request->ip(), 'last_seen_at' => now()],
        );

        if (! $device->wasRecentlyCreated) {
            $device->forceFill(['last_seen_at' => now(), 'ip' => $request->ip()])->save();

            return;
        }

        if ($isFirstDeviceEver) {
            return;
        }

        $this->notifier->toUser($user, 'security.new_device', [
            'device' => $device->label ?? 'an unknown device',
        ], ['link' => '/settings']);
    }

    /**
     * "Chrome on Android" — a human summary, best effort. Never the raw UA.
     */
    public static function label(string $agent): string
    {
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/'), str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        return match (true) {
            $browser !== null && $os !== null => "{$browser} on {$os}",
            $browser !== null => $browser,
            $os !== null => $os,
            default => 'Unknown device',
        };
    }
}
