<?php

namespace App\Services\Academics;

use App\Enums\MarklistStatus;
use App\Models\GradingScale;
use App\Models\GradingScaleBand;
use App\Models\Marklist;
use App\Models\MarklistItem;
use Illuminate\Support\Collection;

class RankingService
{
    public function recalculateMarklist(Marklist $marklist): void
    {
        $items = $marklist->items()->get();

        $scored = $items
            ->filter(fn (MarklistItem $item) => $item->score !== null)
            ->sortByDesc(fn (MarklistItem $item) => $this->percent($item))
            ->values();

        $rank = 0;
        $seen = 0;
        $previous = null;

        foreach ($scored as $item) {
            $seen++;
            $percent = round($this->percent($item), 4);
            if ($previous === null || $percent !== $previous) {
                $rank = $seen;
                $previous = $percent;
            }
            if ((int) $item->rank !== $rank) {
                $item->forceFill(['rank' => $rank])->save();
            }
        }

        foreach ($items as $item) {
            if ($item->score === null && $item->rank !== null) {
                $item->forceFill(['rank' => null])->save();
            }
        }
    }

    public function letterGrade(?float $score, ?int $maxScore = 100): ?string
    {
        if ($score === null) {
            return null;
        }

        $percent = $this->percentFromValues($score, $maxScore);

        return $this->letterFromPercent($percent);
    }

    public function letterFromPercent(?float $percent): ?string
    {
        if ($percent === null) {
            return null;
        }

        $band = $this->bands()->first(fn (GradingScaleBand $band) => $band->contains($percent));

        return $band?->label;
    }

    /**
     * @return Collection<int, GradingScaleBand>
     */
    public function bands(): Collection
    {
        $scale = GradingScale::defaultScale();

        return $scale?->bands ?? collect();
    }

    public function percent(MarklistItem $item): float
    {
        return $this->percentFromValues($item->score, $item->max_score);
    }

    public function percentFromValues(?float $score, ?int $maxScore): float
    {
        if ($score === null) {
            return 0.0;
        }

        $max = $maxScore && $maxScore > 0 ? $maxScore : 100;

        return round(($score / $max) * 100, 2);
    }

    /**
     * Per-member average (percent) and competition rank for a class + semester.
     *
     * @return list<array{member_id: int, average: float, rank: int, subjects: int}>
     */
    public function classSemesterStandings(int $classId, int $termId): array
    {
        $rows = MarklistItem::query()
            ->select('marklist_items.member_id')
            ->selectRaw('AVG(CASE WHEN marklist_items.max_score > 0 THEN (marklist_items.score / marklist_items.max_score) * 100 ELSE marklist_items.score END) as average')
            ->selectRaw('COUNT(marklist_items.id) as subjects')
            ->join('marklists', 'marklists.id', '=', 'marklist_items.marklist_id')
            ->where('marklists.class_id', $classId)
            ->where('marklists.term_id', $termId)
            ->where('marklists.status', MarklistStatus::Approved->value)
            ->whereNotNull('marklist_items.score')
            ->groupBy('marklist_items.member_id')
            ->orderByDesc('average')
            ->get();

        return $this->attachCompetitionRanks($rows->map(fn ($row) => [
            'member_id' => (int) $row->member_id,
            'average' => round((float) $row->average, 2),
            'subjects' => (int) $row->subjects,
        ])->all(), 'average');
    }

    /**
     * Institution-wide aggregates for Education Head reporting.
     *
     * @return array{
     *     students: int,
     *     subjects: int,
     *     marklists: int,
     *     average: float|null,
     *     by_class: list<array{class_id: int, class_name: string, students: int, average: float}>,
     *     by_subject: list<array{subject_id: int, subject_name: string, average: float, students: int}>,
     *     grade_distribution: array<string, int>
     * }
     */
    public function institutionReport(?int $academicYearId = null, ?int $termId = null, ?int $classId = null): array
    {
        $query = MarklistItem::query()
            ->join('marklists', 'marklists.id', '=', 'marklist_items.marklist_id')
            ->join('subjects', 'subjects.id', '=', 'marklists.subject_id')
            ->join('classes', 'classes.id', '=', 'marklists.class_id')
            ->join('terms', 'terms.id', '=', 'marklists.term_id')
            ->where('marklists.status', MarklistStatus::Approved->value)
            ->whereNotNull('marklist_items.score');

        if ($academicYearId) {
            $query->where('terms.academic_year_id', $academicYearId);
        }
        if ($termId) {
            $query->where('marklists.term_id', $termId);
        }
        if ($classId) {
            $query->where('marklists.class_id', $classId);
        }

        $items = $query->get([
            'marklist_items.member_id',
            'marklist_items.score',
            'marklist_items.max_score',
            'marklists.id as marklist_id',
            'marklists.class_id',
            'marklists.subject_id',
            'classes.name as class_name',
            'subjects.name as subject_name',
        ]);

        $percents = $items->map(fn ($item) => $this->percentFromValues((float) $item->score, $item->max_score ? (int) $item->max_score : null));

        $byClass = $items->groupBy('class_id')->map(function (Collection $group) {
            $avg = $group->avg(fn ($item) => $this->percentFromValues((float) $item->score, $item->max_score ? (int) $item->max_score : null));

            return [
                'class_id' => (int) $group->first()->class_id,
                'class_name' => (string) $group->first()->class_name,
                'students' => $group->pluck('member_id')->unique()->count(),
                'average' => round((float) $avg, 2),
            ];
        })->sortByDesc('average')->values()->all();

        $bySubject = $items->groupBy('subject_id')->map(function (Collection $group) {
            $avg = $group->avg(fn ($item) => $this->percentFromValues((float) $item->score, $item->max_score ? (int) $item->max_score : null));

            return [
                'subject_id' => (int) $group->first()->subject_id,
                'subject_name' => (string) $group->first()->subject_name,
                'students' => $group->pluck('member_id')->unique()->count(),
                'average' => round((float) $avg, 2),
            ];
        })->sortByDesc('average')->values()->all();

        $distribution = [];
        foreach ($this->bands() as $band) {
            $distribution[$band->label] = 0;
        }
        foreach ($percents as $percent) {
            $label = $this->letterFromPercent($percent) ?? '—';
            $distribution[$label] = ($distribution[$label] ?? 0) + 1;
        }

        return [
            'students' => $items->pluck('member_id')->unique()->count(),
            'subjects' => $items->pluck('subject_id')->unique()->count(),
            'marklists' => $items->pluck('marklist_id')->unique()->count(),
            'average' => $percents->isNotEmpty() ? round((float) $percents->avg(), 2) : null,
            'by_class' => $byClass,
            'by_subject' => $bySubject,
            'grade_distribution' => $distribution,
        ];
    }

    /**
     * Student portal payload: each approved subject with score, max, letter, peer rank.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     semester_average: float|null,
     *     year_average: float|null,
     *     overall_rank: int|null,
     *     cohort_size: int
     * }
     */
    public function studentResults(int $memberId): array
    {
        $items = MarklistItem::query()
            ->with(['marklist.term.academicYear', 'marklist.subject', 'marklist.class'])
            ->where('member_id', $memberId)
            ->whereHas('marklist', fn ($query) => $query->where('status', MarklistStatus::Approved->value))
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $max = $item->max_score ?: $item->marklist?->subject?->max_score ?: 100;
            $percent = $item->score !== null ? $this->percentFromValues((float) $item->score, (int) $max) : null;

            $rows[] = [
                'id' => $item->id,
                'subject' => $item->marklist?->subject?->name ?? 'Subject',
                'class' => $item->marklist?->class?->name,
                'term' => $item->marklist?->term?->name,
                'academic_year' => $item->marklist?->term?->academicYear?->name,
                'score' => $item->score !== null ? (float) $item->score : null,
                'max_score' => (int) $max,
                'percent' => $percent,
                'letter' => $percent !== null ? $this->letterFromPercent($percent) : null,
                'rank' => $item->rank,
                'peers' => $item->marklist?->items()->whereNotNull('score')->count() ?? 0,
                'conduct' => $item->conduct?->value,
                'memorization' => $item->memorization?->value,
                'participation' => $item->participation?->value,
            ];
        }

        $scored = collect($rows)->filter(fn (array $row) => $row['percent'] !== null);
        $latest = $items->sortByDesc(fn (MarklistItem $item) => $item->marklist?->term_id)->first();
        $termId = $latest?->marklist?->term_id;
        $classId = $latest?->marklist?->class_id;
        $yearId = $latest?->marklist?->term?->academic_year_id;

        $semesterAverage = $scored
            ->filter(fn (array $row) => $row['term'] === ($latest?->marklist?->term?->name))
            ->avg('percent');

        $yearAverage = $items
            ->filter(fn (MarklistItem $item) => $item->marklist?->term?->academic_year_id === $yearId && $item->score !== null)
            ->avg(fn (MarklistItem $item) => $this->percent($item));

        $standings = ($classId && $termId) ? $this->classSemesterStandings($classId, $termId) : [];
        $own = collect($standings)->firstWhere('member_id', $memberId);

        return [
            'items' => $rows,
            'semester_average' => $semesterAverage !== null ? round((float) $semesterAverage, 2) : null,
            'year_average' => $yearAverage !== null ? round((float) $yearAverage, 2) : null,
            'overall_rank' => $own['rank'] ?? null,
            'cohort_size' => count($standings),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachCompetitionRanks(array $rows, string $scoreKey): array
    {
        usort($rows, fn ($a, $b) => $b[$scoreKey] <=> $a[$scoreKey]);

        $rank = 0;
        $seen = 0;
        $previous = null;

        foreach ($rows as $i => $row) {
            $seen++;
            $value = (float) $row[$scoreKey];
            if ($previous === null || $value !== $previous) {
                $rank = $seen;
                $previous = $value;
            }
            $rows[$i]['rank'] = $rank;
        }

        return $rows;
    }
}
