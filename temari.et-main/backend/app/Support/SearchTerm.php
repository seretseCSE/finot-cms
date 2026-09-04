<?php

namespace App\Support;

use Closure;

/**
 * The one way a list endpoint turns what a user typed into a WHERE clause.
 *
 * Ethiopian names live in separate columns (`first_name` / `father_name` /
 * `grandfather_name`), so the naive `column ilike '%needle%'` per column can
 * never match a full name: "Abdi Fikre Gemeda" is in no single column, and the
 * user sees an empty table for a record they are looking straight at.
 *
 * So a query is matched WORD BY WORD: every word the user typed must match
 * somewhere in the row (AND across words, OR across the columns each word is
 * tried against). The whole phrase is still tried intact first, so values that
 * legitimately contain spaces ("Addis Ababa Academy", a receipt description)
 * keep matching exactly as before — this is a strict superset of the old
 * behaviour, never narrower.
 *
 * The same word-by-word rule powers the ⌘K palette
 * (`App\Services\GlobalSearchService`); this is the list-endpoint half of it.
 */
final class SearchTerm
{
    /**
     * Apply a search to $query. $match receives an isolated builder plus ONE
     * needle and should `where(...)->orWhere(...)` across everything that
     * needle may hit; SearchTerm handles the AND-across-words grouping.
     *
     * A blank query is a no-op.
     *
     * @param  Closure(mixed, string): void  $match
     */
    public static function apply(mixed $query, ?string $raw, Closure $match): void
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return;
        }

        $words = self::words($raw);

        $query->where(function ($outer) use ($raw, $words, $match): void {
            // The phrase as typed — one column may hold all of it.
            $outer->where(fn ($group) => $match($group, $raw));

            if (count($words) > 1) {
                $outer->orWhere(function ($all) use ($words, $match): void {
                    foreach ($words as $word) {
                        $all->where(fn ($group) => $match($group, $word));
                    }
                });
            }
        });
    }

    /**
     * Split what the user typed into words.
     *
     * @return list<string>
     */
    public static function words(string $raw): array
    {
        return preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * A `%…%` ILIKE needle with the user's own wildcards neutralised, so typing
     * `%` searches for a percent sign instead of matching every row.
     */
    public static function contains(string $value): string
    {
        return '%'.addcslashes($value, '\%_').'%';
    }
}
