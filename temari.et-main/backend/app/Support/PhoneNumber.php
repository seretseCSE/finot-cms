<?php

namespace App\Support;

/**
 * The single source of truth for Ethiopian phone numbers across Temari.
 *
 * Mobile (accounts, SMS, guardians — the default everywhere):
 *
 *   09XXXXXXXX        Ethio Telecom  (local)
 *   07XXXXXXXX        Safaricom ET   (local)
 *   +2519XXXXXXXX     Ethio Telecom  (E.164)     also 2519XXXXXXXX
 *   +2517XXXXXXXX     Safaricom ET   (E.164)     also 2517XXXXXXXX
 *   9XXXXXXXX / 7XXXXXXXX (leading zero dropped)
 *
 * …and normalise ALL of them to one canonical LOCAL form (`09…` / `07…`) so a
 * user who registers as `0912…` and later types `+251912…` is the same account.
 * Storage stays local; `forSms()` produces the international shape on demand.
 *
 * Contact / office (school official line only — also accepts geographic landlines):
 *
 *   011XXXXXXX        Addis Ababa landline (local)
 *   +251 11 662 98 00 international landline shapes
 *   plus every mobile shape above
 *
 * Safaricom (`07…`) acceptance is gated by `sms.allow_safaricom` (default OFF):
 * the SMS provider only delivers to Ethio Telecom, so until the flag flips,
 * every normaliser here treats `07…` as invalid — which cascades to validation
 * rules, model mutators, login, signup and `forSms()` automatically.
 */
class PhoneNumber
{
    /** Canonical stored form: 10 digits, `09…` (Ethio Telecom) or `07…` (Safaricom ET). */
    public const LOCAL_REGEX = '/^0[79]\d{8}$/';

    /** Ethio Telecom only — the accepted set while `sms.allow_safaricom` is off. */
    public const ETHIO_TELECOM_LOCAL_REGEX = '/^09\d{8}$/';

    /**
     * Geographic landlines (Ethio Telecom NDCs 1–6), e.g. Addis `011…`.
     * Never used for accounts / SMS — only school (and similar) office lines.
     */
    public const LANDLINE_LOCAL_REGEX = '/^0[1-6]\d{8}$/';

    /**
     * Whether Safaricom (`07…`) numbers are currently accepted anywhere.
     */
    public static function allowSafaricom(): bool
    {
        return (bool) config('sms.allow_safaricom', false);
    }

    /**
     * Reduce any accepted shape to the canonical local form, or null when the
     * value cannot be a valid (and currently deliverable) Ethiopian mobile.
     */
    public static function normalize(?string $raw): ?string
    {
        $local = self::toLocalDigits($raw);

        if ($local === null) {
            return null;
        }

        return preg_match(self::mobileRegex(), $local) === 1 ? $local : null;
    }

    /**
     * Mobile OR geographic landline — for school official contact lines that
     * may be an office number like `+251 11 662 98 00` / `0116629800`.
     */
    public static function normalizeContact(?string $raw): ?string
    {
        $local = self::toLocalDigits($raw);

        if ($local === null) {
            return null;
        }

        if (preg_match(self::mobileRegex(), $local) === 1) {
            return $local;
        }

        return preg_match(self::LANDLINE_LOCAL_REGEX, $local) === 1 ? $local : null;
    }

    /**
     * Whether the value is an acceptable Ethiopian mobile number in any shape.
     */
    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /**
     * Whether the value is an acceptable Ethiopian mobile or office landline.
     */
    public static function isValidContact(?string $raw): bool
    {
        return self::normalizeContact($raw) !== null;
    }

    /**
     * Pretty display form, e.g. `0911 234 567`. Falls back to the raw string if
     * it is not a recognisable Ethiopian number.
     */
    public static function format(?string $raw): ?string
    {
        $local = self::normalize($raw);

        if ($local === null) {
            return $raw;
        }

        return $local[0].substr($local, 1, 3).' '.substr($local, 4, 3).' '.substr($local, 7, 3);
    }

    /**
     * Pretty display for contact lines: mobile groups as `0911 234 567`,
     * landlines as `011 662 98 00`.
     */
    public static function formatContact(?string $raw): ?string
    {
        $local = self::normalizeContact($raw);

        if ($local === null) {
            return $raw;
        }

        if (preg_match(self::LOCAL_REGEX, $local) === 1) {
            return self::format($local);
        }

        return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6, 2).' '.substr($local, 8, 2);
    }

    /**
     * International form for SMS gateways: `2519XXXXXXXX` / `2517XXXXXXXX`
     * (no leading '+'). Returns null when the number is not a valid mobile.
     */
    public static function forSms(?string $raw): ?string
    {
        $local = self::normalize($raw);

        return $local === null ? null : '251'.substr($local, 1);
    }

    /**
     * The mobile pattern currently in force — Ethio Telecom only until the
     * SMS provider can deliver to Safaricom (`sms.allow_safaricom`).
     */
    private static function mobileRegex(): string
    {
        return self::allowSafaricom() ? self::LOCAL_REGEX : self::ETHIO_TELECOM_LOCAL_REGEX;
    }

    /**
     * Strip formatting and map any accepted Ethiopian shape to 10 local digits
     * (leading `0`), without deciding mobile vs landline.
     */
    private static function toLocalDigits(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Keep digits and a single leading plus; drop spaces, dashes, parens, dots.
        $trimmed = trim($raw);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        // International: 251 + 9 national digits — with or without the leading '+'.
        if (str_starts_with($digits, '251')) {
            $rest = substr($digits, 3);

            return strlen($rest) === 9 ? '0'.$rest : null;
        }

        if ($hasPlus) {
            // A '+' that isn't +251 is some other country — reject.
            return null;
        }

        if (strlen($digits) === 10 && $digits[0] === '0') {
            return $digits;
        }

        // Leading zero dropped (9 national digits).
        if (strlen($digits) === 9) {
            return '0'.$digits;
        }

        return null;
    }
}
