<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Online tutoring rooms. v1 rides Jitsi (self-hostable later — just change
 * JITSI_BASE_URL): each session gets an unguessable room; the link IS the
 * key, shared only with the two parties. Video is a feature, not a gate —
 * a phone call remains a normal fallback in Ethiopia.
 */
class Meetings
{
    public static function roomUrl(int $engagementId, int $sessionId): string
    {
        $base = rtrim((string) config('services.jitsi.base_url', 'https://meet.jit.si'), '/');

        return "{$base}/temari-tut-{$engagementId}-{$sessionId}-".Str::lower(Str::random(10));
    }
}
