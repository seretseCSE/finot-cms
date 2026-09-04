<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Public-facing person codes (e.g. H8R6WV) printed on ID cards and used to look
 * people up across schools. Never derived from — and never a substitute for —
 * the database id. Codes use an unambiguous uppercase alphabet (no 0/O/1/I) so
 * they survive handwriting and phone calls; always uppercase the user's input
 * before matching.
 */
final class PublicId
{
    public const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const int LENGTH = 6;

    private const int MAX_ATTEMPTS = 10;

    public static function generate(string $table, string $column = 'public_id'): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = self::random();

            if (! DB::table($table)->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException("Unable to allocate a unique public id for [{$table}].");
    }

    /** Normalise user-supplied input for matching (codes are stored uppercase). */
    public static function normalize(string $input): string
    {
        return strtoupper(trim($input));
    }

    private static function random(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
