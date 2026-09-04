<?php

namespace App\Support;

class Ranking
{
    /**
     * Competition / Olympic ranking: 1, 2, 2, 4.
     *
     * @param  list<float|null>  $scores  Descending preferred; nulls get null ranks
     * @return list<int|null>
     */
    public static function competition(array $scores): array
    {
        $indexed = [];
        foreach ($scores as $i => $score) {
            $indexed[] = ['i' => $i, 'score' => $score];
        }

        usort($indexed, function ($a, $b) {
            if ($a['score'] === null && $b['score'] === null) {
                return $a['i'] <=> $b['i'];
            }
            if ($a['score'] === null) {
                return 1;
            }
            if ($b['score'] === null) {
                return -1;
            }

            return $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i'];
        });

        $ranks = array_fill(0, count($scores), null);
        $place = 0;
        $lastScore = null;
        $seen = 0;

        foreach ($indexed as $item) {
            $seen++;
            if ($item['score'] === null) {
                $ranks[$item['i']] = null;
                continue;
            }

            if ($lastScore === null || (float) $item['score'] !== (float) $lastScore) {
                $place = $seen;
                $lastScore = $item['score'];
            }

            $ranks[$item['i']] = $place;
        }

        return $ranks;
    }
}
