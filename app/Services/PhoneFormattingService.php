<?php

namespace App\Services;

class PhoneFormattingService
{
    /**
     * Get the phone number prefix from config.
     *
     * @return string The phone prefix
     */
    public static function prefix(): string
    {
        return config('finot.phone_prefix', '+251');
    }

    /**
     * Strip the country prefix and leading zeros from a phone number for display/editing.
     *
     * @param string|null $state The phone number state
     * @return string|null The formatted phone number
     */
    public static function formatStateUsing(?string $state): ?string
    {
        $prefix = self::prefix();

        return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
    }

    /**
     * Prepend the country prefix to a phone number before saving.
     *
     * @param string|null $state The phone number state
     * @return string|null The phone number with prefix
     */
    public static function dehydrateStateUsing(?string $state): ?string
    {
        return $state ? self::prefix() . $state : null;
    }

    /**
     * Format a phone number for display with prefix.
     *
     * @param string|null $phone The phone number
     * @return string The formatted phone number
     */
    public static function formatForDisplay(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $prefix = self::prefix();

        if (! str_starts_with($phone, $prefix)) {
            $phone = $prefix . ltrim($phone, '0');
        }

        return $phone;
    }

    /**
     * Helper text for phone input fields.
     *
     * @return string The helper text
     */
    public static function helperText(): string
    {
        return 'Enter 9 digits after ' . self::prefix();
    }

    /**
     * National 9-digit number (no country code, no leading zeros).
     */
    public static function nationalDigits(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if ($digits === '') {
            return null;
        }

        $prefixDigits = preg_replace('/\D+/', '', self::prefix()) ?? '';
        if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
            $digits = substr($digits, strlen($prefixDigits));
        }

        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : null;
    }

    /**
     * Full stored phone (+251…) for authentication lookup.
     */
    public static function normalizeForAuth(?string $input): ?string
    {
        $national = self::nationalDigits($input);

        return $national ? self::prefix().$national : null;
    }
}
