<?php

namespace App\Services\Reports;

use App\Models\StudentTermResult;
use App\Support\Ranking;

/**
 * Per-subject section ranks for report cards. The freeze stamps them into
 * the breakdown since July 2026 — but rows frozen BEFORE that carry none,
 * and a school flipping the subject-ranks setting on must not need to
 * recompute (and rewrite) closed history to print them. This resolver fills
 * the GAPS at print time from the same frozen section rows (the read-time
 * rank precedent the yearly roster set); a rank already frozen in the
 * breakdown always wins.
 */
class SubjectRankResolver
{
    /**
     * Fill missing per-subject ranks on card payloads (reportCard shape).
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public function fill(array $cards, int $termId): array
    {
        $studentIds = collect($cards)
            ->map(fn (array $card) => $card['student']['id'] ?? null)
            ->filter()
            ->values();

        if ($studentIds->isEmpty()) {
            return $cards;
        }

        // The card students' sections, then EVERY frozen row of those
        // sections — a rank is only honest over the whole cohort.
        $sectionByStudent = StudentTermResult::query()
            ->where('term_id', $termId)
            ->whereIn('student_id', $studentIds)
            ->pluck('section_id', 'student_id');

        $rows = StudentTermResult::query()
            ->where('term_id', $termId)
            ->whereIn('section_id', $sectionByStudent->values()->unique()->filter())
            ->get(['student_id', 'section_id', 'breakdown']);

        // section → subject → student → total, ranked per cohort.
        $ranks = [];
        $counts = [];

        foreach ($rows->groupBy('section_id') as $sectionId => $sectionRows) {
            $totals = [];

            foreach ($sectionRows as $row) {
                foreach ($row->breakdown as $line) {
                    if (($line['total'] ?? null) !== null) {
                        $totals[$line['subject_id']][$row->student_id] = (float) $line['total'];
                    }
                }
            }

            foreach ($totals as $subjectId => $byStudent) {
                $ranks[$sectionId][$subjectId] = Ranking::competition(
                    collect($byStudent),
                    fn (float $total): float => $total,
                );
                $counts[$sectionId][$subjectId] = count($byStudent);
            }
        }

        return collect($cards)
            ->map(function (array $card) use ($sectionByStudent, $ranks, $counts): array {
                $studentId = $card['student']['id'] ?? null;
                $sectionId = $studentId !== null ? $sectionByStudent->get($studentId) : null;

                if ($sectionId === null) {
                    return $card;
                }

                $card['subjects'] = collect($card['subjects'] ?? [])
                    ->map(function ($line) use ($ranks, $counts, $sectionId, $studentId) {
                        $line = (array) $line;

                        // The frozen snapshot's own rank always wins.
                        if (($line['rank'] ?? null) === null && ($line['total'] ?? null) !== null) {
                            $line['rank'] = $ranks[$sectionId][$line['subject_id']][$studentId] ?? null;
                            $line['rank_of'] = $line['rank'] !== null
                                ? ($counts[$sectionId][$line['subject_id']] ?? null)
                                : null;
                        }

                        return $line;
                    })
                    ->values()
                    ->all();

                return $card;
            })
            ->values()
            ->all();
    }
}
