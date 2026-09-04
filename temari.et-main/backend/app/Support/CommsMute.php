<?php

namespace App\Support;

/**
 * Process-wide mute for OUTBOUND comms (SMS + email) during bulk operations —
 * the in-app feed is never muted (ADR-018: the feed is the system of record).
 *
 * A bulk student import can touch thousands of families; a single wrong file
 * must never become a paid SMS storm, so the import job runs inside
 * CommsMute::run() unless the operator explicitly enabled sending. Both the
 * Notifier's SMS/email legs and the bespoke senders (RegistrationNotifier)
 * check active().
 */
class CommsMute
{
    private static bool $active = false;

    /**
     * Run $fn with outbound comms muted (restores the previous state even on
     * throw, so nested use is safe).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public static function run(callable $fn): mixed
    {
        $previous = self::$active;
        self::$active = true;

        try {
            return $fn();
        } finally {
            self::$active = $previous;
        }
    }

    public static function active(): bool
    {
        return self::$active;
    }
}
