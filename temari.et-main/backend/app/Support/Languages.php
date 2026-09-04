<?php

namespace App\Support;

/**
 * Languages a student may speak at home — used for the students.languages
 * field. This is NOT the UI locale list (that is en/am/om only); it is the
 * catalog registrars pick from during registration. Codes are ISO 639 where
 * one exists.
 */
final class Languages
{
    /** @var array<string, string> code => English label */
    public const array ALL = [
        'am' => 'Amharic',
        'om' => 'Afan Oromo',
        'ti' => 'Tigrinya',
        'so' => 'Somali',
        'sid' => 'Sidama',
        'wal' => 'Wolaytta',
        'aa' => 'Afar',
        'gur' => 'Gurage',
        'har' => 'Harari',
        'gez' => "Ge'ez",
        'en' => 'English',
        'ar' => 'Arabic',
        'fr' => 'French',
        'other' => 'Other',
    ];

    public const string DEFAULT = 'am';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function isValid(string $code): bool
    {
        return array_key_exists($code, self::ALL);
    }

    /** UI locales users may pick as preferred_language (v1 interface languages). */
    public const array UI_LOCALES = ['en', 'am', 'om'];
}
