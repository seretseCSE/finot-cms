<?php

namespace App\Services\Analytics;

use App\Jobs\SendAnalyticsEventJob;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PostHog\PostHog;
use Throwable;

/**
 * Product analytics + error tracking (PostHog). The single entry point for
 * every backend capture: "who did what when", scoped by school/branch so
 * every event slices by tenant.
 *
 * Fire-and-forget by design — captures are queued (after commit) so a
 * request never waits on the analytics API, everything no-ops when
 * POSTHOG_KEY is unset (local dev, tests), and a capture failure can never
 * take a request down with it.
 */
class Analytics
{
    /** Suppresses re-entrant captures while the send job itself runs. */
    public static bool $sending = false;

    public static function enabled(): bool
    {
        return filled(config('services.posthog.key'));
    }

    /**
     * Record a product event. Event names are dot-namespaced like the
     * activity log ("payment.recorded", "marklist.approved").
     *
     * @param  array<string, mixed>  $properties
     */
    public static function capture(
        ?User $actor,
        string $event,
        array $properties = [],
        ?int $schoolId = null,
        ?int $branchId = null,
    ): void {
        if (! self::enabled() || self::$sending) {
            return;
        }

        $groups = array_filter([
            'school' => $schoolId !== null ? 'school:'.$schoolId : null,
            'branch' => $branchId !== null ? 'branch:'.$branchId : null,
        ]);

        self::queue([
            'type' => 'capture',
            'distinctId' => self::distinctId($actor),
            'event' => $event,
            'groups' => $groups,
            'properties' => array_filter([
                ...self::scrub($properties),
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                // Server events without a person must not mint anonymous profiles.
                '$process_person_profile' => $actor === null ? false : null,
                'source' => 'backend',
            ], fn ($v) => $v !== null),
        ]);
    }

    /**
     * Stamp/refresh the person profile — call on signup and login so the
     * frontend session and backend events merge into one person.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function identify(User $user, array $extra = []): void
    {
        if (! self::enabled() || self::$sending) {
            return;
        }

        self::queue([
            'type' => 'identify',
            'distinctId' => (string) $user->id,
            'properties' => array_filter([
                'name' => $user->name,
                'preferred_language' => $user->preferred_language,
                ...$extra,
            ], fn ($v) => $v !== null),
        ]);
    }

    /**
     * Ship one log record to PostHog Logs (OTLP). Called by the `posthog`
     * log channel (App\Logging\PostHogLogHandler) — never directly; write
     * through Laravel's logger and the channel forwards.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function log(string $level, string $message, array $attributes = []): void
    {
        if (! self::enabled() || self::$sending) {
            return;
        }

        try {
            $actor = Auth::user();
        } catch (Throwable) {
            $actor = null; // Logging can fire before auth is booted.
        }

        self::queue([
            'type' => 'log',
            'level' => $level,
            'message' => Str::limit($message, 2000),
            'time_unix_nano' => (string) (int) (microtime(true) * 1e9),
            'attributes' => array_filter([
                ...self::scrub($attributes),
                'user_id' => $actor?->id,
                'env' => app()->environment(),
            ], fn ($v) => $v !== null),
        ]);
    }

    /**
     * Forward an exception to PostHog error tracking, through the SDK's
     * native captureException (full frame format, chained exceptions,
     * in-app detection). Sent synchronously — a Throwable can't cross the
     * queue, exceptions are rare, and the failing request is already the
     * slow path. Wired once in bootstrap/app.php — never call from a catch
     * block that also rethrows, or the error would be reported twice.
     *
     * @param  array<string, mixed>  $context
     */
    public static function captureException(Throwable $e, array $context = []): void
    {
        if (! self::enabled() || self::$sending) {
            return;
        }

        self::$sending = true;

        try {
            $actor = Auth::user();
        } catch (Throwable) {
            $actor = null; // Auth may not be booted yet (early bootstrap failures).
        }

        try {
            PostHog::init(config('services.posthog.key'), [
                'host' => config('services.posthog.host'),
            ]);

            // Null distinct id = per-event UUID, no anonymous person minted.
            PostHog::captureException($e, $actor !== null ? (string) $actor->id : null, [
                ...self::scrub($context),
                'url' => request()?->fullUrl(),
                'source' => 'backend',
            ]);

            PostHog::flush();
        } catch (Throwable) {
            // Error reporting must never worsen the failure being reported.
        } finally {
            self::$sending = false;
        }
    }

    private static function distinctId(?User $actor): string
    {
        return $actor !== null ? (string) $actor->id : 'backend';
    }

    /**
     * Analytics carries context, never payloads: keep short scalars only so
     * no free-text (marks, health notes, messages) ever leaves the app.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function scrub(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$key] = $value;
            } elseif (is_string($value) && mb_strlen($value) <= 200) {
                $clean[$key] = $value;
            } elseif ($value instanceof \BackedEnum) {
                $clean[$key] = $value->value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function queue(array $payload): void
    {
        try {
            SendAnalyticsEventJob::dispatch($payload)->afterCommit();
        } catch (Throwable) {
            // Analytics must never break the request that produced the event.
        }
    }
}
