<?php

namespace App\Services;

class PhoneFormattingService
{
    public static function prefix(): string
    {
        return config('finot.phone_prefix', '+251');
    }

    /**
     * Strip the country prefix and leading zeros from a phone number for display/editing.
     */
    public static function formatStateUsing(?string $state): ?string
    {
        $prefix = self::prefix();

        return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
    }

    /**
     * Prepend the country prefix to a phone number before saving.
     */
    public static function dehydrateStateUsing(?string $state): ?string
    {
        return $state ? self::prefix() . $state : null;
    }

    /**
     * Format a phone number for display with prefix.
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
     */
    public static function helperText(): string
    {
        return 'Enter 9 digits after ' . self::prefix();
    }
}
