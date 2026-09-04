<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Competition ("olympic") ranking: 1, 2, 2, 4 — ties share a rank and the
 * next distinct value skips the tied positions. This is the ranking every
 * Ethiopian roster/report card uses, so the term-close freeze and the
 * read-time yearly roster must agree — both call this helper.
 */
final class Ranking
{
    /**
     * Rank the collection's items by a value, descending. Items whose value
     * is null are unranked (absent from the result). Returns collection key
     * → rank, so callers keyBy the identifier they need first.
     *
     * @template TKey of array-key
     *
     * @param  Collection<TKey, mixed>  $items
     * @param  callable(mixed): (float|int|null)  $value
     * @return array<TKey, int>
     */
    public static function competition(Collection $items, callable $value): array
    {
        $scored = $items
            ->map(fn ($item) => $value($item))
            ->reject(fn ($v) => $v === null)
            ->map(fn ($v) => (float) $v)
            ->sortDesc();

        $ranks = [];
        $position = 0;
        $prevValue = null;
        $prevRank = 0;

        foreach ($scored as $key => $v) {
            $position++;
            $prevRank = ($prevValue !== null && $v === $prevValue) ? $prevRank : $position;
            $ranks[$key] = $prevRank;
            $prevValue = $v;
        }

        return $ranks;
    }
}
