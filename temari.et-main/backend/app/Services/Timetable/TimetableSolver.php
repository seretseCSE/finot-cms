<?php

namespace App\Services\Timetable;

use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use Illuminate\Support\Facades\DB;

/**
 * Automatic timetable generation — the FET/aSc recipe, sized for Ethiopian
 * schools: activities (subject assignments split into single/double-period
 * lessons) are placed greedily most-constrained-first under the HARD
 * constraints, then a time-boxed hill climb with localized penalty deltas
 * shakes the grid toward the SOFT preferences. Locked slots never move;
 * unlocked ones are regenerated. A branch of ~40 sections solves in seconds.
 *
 * Hard: section/teacher clash, teacher unavailability windows, teacher daily
 * maximum, block contiguity (doubles never straddle a break), bell-schedule
 * cells only.
 *
 * Soft (penalties): heavy subjects late in the day · heavy subject in
 * Friday's last period · the same subject twice in one day · teacher idle
 * gaps · uneven teacher day loads · more than 3 consecutive teaching periods
 * · more lab/gym lessons in a cell than the branch has rooms.
 *
 * Rooms: subjects with a `room_type` get a free room of that type booked
 * automatically at persist time (locked slots keep theirs); when every room
 * is taken the lesson falls back to the section's own classroom.
 */
class TimetableSolver
{
    private const P_HEAVY_LATE = 2;      // × weight-3 × period position

    private const P_HEAVY_FRIDAY_LAST = 40;

    private const P_SUBJECT_REPEAT = 25; // per extra lesson of a subject in one day

    private const P_TEACHER_GAP = 3;     // per idle period inside a teaching day

    private const P_TEACHER_BALANCE = 1; // × daily load² (convex ⇒ even spread wins)

    private const P_LONG_RUN = 10;       // per consecutive period beyond 3

    private const P_NO_ROOM = 30;        // required special room not free in that cell

    private const MAX_RUN = 3;

    private const TIME_BUDGET_SECONDS = 8;

    private TimetableContext $ctx;

    private int $teacherDailyMax = ConstraintValidator::TEACHER_DAILY_MAX;

    /** @var array<int, int> period number → ordinal position (0-based) */
    private array $periodPos = [];

    /** @var list<array{assignment_id: int, day: int, start: int, length: int, locked: bool}> */
    private array $placements = [];

    /** @var array<string, int> "section-day-period" → placement idx (occupancy) */
    private array $sectionBusy = [];

    /** @var array<string, int> "employee-day-period" → placement idx */
    private array $teacherBusy = [];

    /** @var array<string, int> "roomType-day-period" → lessons wanting that room type */
    private array $roomDemand = [];

    /**
     * @param  array{teacher_daily_max?: int}  $options
     * @return array{score: int, conflicts: list<array<string, mixed>>, placed: int}
     */
    public function solve(TimetableVersion $version, array $options = []): array
    {
        $this->ctx = new TimetableContext($version);
        $this->teacherDailyMax = max(1, (int) ($options['teacher_daily_max'] ?? ConstraintValidator::TEACHER_DAILY_MAX));
        $this->periodPos = array_flip($this->ctx->periods);
        $this->placements = [];
        $this->sectionBusy = [];
        $this->teacherBusy = [];
        $this->roomDemand = [];

        mt_srand($version->id * 7919 + count($this->ctx->periods));

        if ($this->ctx->periods === [] || $this->ctx->days === []) {
            return ['score' => 0, 'conflicts' => [['code' => 'no_bell_schedule']], 'placed' => 0];
        }

        // Assignments exist only above 0 periods_per_week (context filter) —
        // an empty set means the teaching grid has no weekly loads yet.
        if ($this->ctx->assignments->isEmpty()) {
            return ['score' => 0, 'conflicts' => [['code' => 'no_activities']], 'placed' => 0];
        }

        $remaining = $this->seedLockedPlacements();
        $activities = $this->buildActivities($remaining);

        // Most-constrained-first: long blocks, then scarce teachers.
        usort($activities, function (array $a, array $b): int {
            return [$b['length'], $b['weight'], $a['slack']] <=> [$a['length'], $a['weight'], $b['slack']];
        });

        $conflicts = [];

        foreach ($activities as $activity) {
            if (! $this->placeGreedy($activity)) {
                $conflicts[] = [
                    'code' => 'unplaced',
                    'subject_assignment_id' => $activity['assignment_id'],
                    'subject' => $this->assignment($activity['assignment_id'])->subject?->name,
                    'section' => $this->assignment($activity['assignment_id'])->section?->name,
                    'length' => $activity['length'],
                ];
            }
        }

        $this->improve();
        $score = $this->totalScore();

        $this->persist($version);

        return [
            'score' => $score,
            'conflicts' => $conflicts,
            'placed' => count(array_filter($this->placements, fn ($p) => ! $p['locked'])),
        ];
    }

    // ─────────────────────────── setup ───────────────────────────

    /**
     * Locked slots survive regeneration: adjacent same-assignment locked
     * cells merge into placements; the leftover demand comes back per
     * assignment.
     *
     * @return array<int, int> assignment_id → periods still to place
     */
    private function seedLockedPlacements(): array
    {
        $remaining = [];

        foreach ($this->ctx->assignments as $assignment) {
            $remaining[$assignment->id] = (int) $assignment->periods_per_week;
        }

        $locked = $this->ctx->slots
            ->filter(fn (TimetableSlot $s) => $s->is_locked)
            ->sortBy([['day_of_week', 'asc'], ['period_number', 'asc']])
            ->groupBy(fn (TimetableSlot $s) => $s->subject_assignment_id.'-'.$s->day_of_week);

        foreach ($locked as $group) {
            $run = [];

            foreach ($group as $slot) {
                if (! isset($remaining[$slot->subject_assignment_id])) {
                    continue;
                }

                if ($run !== [] && $this->ctx->isAdjacent(end($run)->period_number, $slot->period_number)) {
                    $run[] = $slot;
                } else {
                    $this->commitLockedRun($run, $remaining);
                    $run = [$slot];
                }
            }

            $this->commitLockedRun($run, $remaining);
        }

        return array_map(fn (int $n) => max(0, $n), $remaining);
    }

    /** @param  list<TimetableSlot>  $run */
    private function commitLockedRun(array $run, array &$remaining): void
    {
        if ($run === []) {
            return;
        }

        $first = $run[0];
        $this->addPlacement($first->subject_assignment_id, $first->day_of_week, $first->period_number, count($run), true);
        $remaining[$first->subject_assignment_id] -= count($run);
    }

    /**
     * @param  array<int, int>  $remaining
     * @return list<array{assignment_id: int, length: int, weight: int, slack: int}>
     */
    private function buildActivities(array $remaining): array
    {
        $activities = [];

        foreach ($this->ctx->assignments as $assignment) {
            $left = $remaining[$assignment->id] ?? 0;
            $block = max(1, (int) $assignment->block_size);
            $slack = $this->teacherSlack($assignment->employee_id);
            $weight = (int) ($assignment->subject->weight ?? 3);

            while ($left >= $block && $block > 1) {
                $activities[] = ['assignment_id' => $assignment->id, 'length' => $block, 'weight' => $weight, 'slack' => $slack];
                $left -= $block;
            }

            for ($i = 0; $i < $left; $i++) {
                $activities[] = ['assignment_id' => $assignment->id, 'length' => 1, 'weight' => $weight, 'slack' => $slack];
            }
        }

        return $activities;
    }

    /** How many weekly cells this teacher can even sit in — scarcity metric. */
    private function teacherSlack(?int $employeeId): int
    {
        if ($employeeId === null) {
            return PHP_INT_MAX;
        }

        $free = 0;

        foreach ($this->ctx->days as $day) {
            foreach ($this->ctx->periods as $period) {
                if (! $this->ctx->teacherBlocked($employeeId, $day, $period)) {
                    $free++;
                }
            }
        }

        return $free;
    }

    // ─────────────────────────── greedy ───────────────────────────

    /** @param  array{assignment_id: int, length: int}  $activity */
    private function placeGreedy(array $activity): bool
    {
        $best = null;
        $bestCost = PHP_INT_MAX;

        foreach ($this->feasibleCells($activity['assignment_id'], $activity['length']) as [$day, $start]) {
            $cost = $this->placementCost($activity['assignment_id'], $day, $start, $activity['length'])
                + mt_rand(0, 4); // tiny noise → varied ties, varied grids

            if ($cost < $bestCost) {
                $bestCost = $cost;
                $best = [$day, $start];
            }
        }

        if ($best === null) {
            return false;
        }

        $this->addPlacement($activity['assignment_id'], $best[0], $best[1], $activity['length'], false);

        return true;
    }

    /**
     * @return iterable<array{0: int, 1: int}>
     */
    private function feasibleCells(int $assignmentId, int $length): iterable
    {
        foreach ($this->ctx->days as $day) {
            foreach ($this->ctx->periods as $start) {
                if ($this->fits($assignmentId, $day, $start, $length)) {
                    yield [$day, $start];
                }
            }
        }
    }

    private function fits(int $assignmentId, int $day, int $start, int $length): bool
    {
        $assignment = $this->assignment($assignmentId);
        $periods = $this->runFrom($start, $length);

        if ($periods === null) {
            return false;
        }

        foreach ($periods as $period) {
            if (isset($this->sectionBusy[$assignment->section_id."-{$day}-{$period}"])) {
                return false;
            }

            if ($assignment->employee_id !== null) {
                if (isset($this->teacherBusy[$assignment->employee_id."-{$day}-{$period}"])) {
                    return false;
                }

                if ($this->ctx->teacherBlocked($assignment->employee_id, $day, $period)) {
                    return false;
                }
            }
        }

        if ($assignment->employee_id !== null
            && $this->teacherDayLoad($assignment->employee_id, $day) + $length > $this->teacherDailyMax) {
            return false;
        }

        return true;
    }

    /** The consecutive class periods starting at $start, or null if a break interrupts. */
    private function runFrom(int $start, int $length): ?array
    {
        $periods = [$start];

        if (! isset($this->periodPos[$start])) {
            return null;
        }

        $current = $start;

        for ($i = 1; $i < $length; $i++) {
            $pos = $this->periodPos[$current] + 1;
            $next = $this->ctx->periods[$pos] ?? null;

            if ($next === null || ! $this->ctx->isAdjacent($current, $next)) {
                return null;
            }

            $periods[] = $next;
            $current = $next;
        }

        return $periods;
    }

    // ─────────────────────────── scoring ───────────────────────────

    private function placementCost(int $assignmentId, int $day, int $start, int $length): int
    {
        $assignment = $this->assignment($assignmentId);
        $employee = $assignment->employee_id;

        $before = $employee !== null ? $this->teacherDayScore($employee, $day) : 0;

        $idx = $this->addPlacement($assignmentId, $day, $start, $length, false);

        $cost = $this->slotScore($idx)
            + ($employee !== null ? $this->teacherDayScore($employee, $day) - $before : 0)
            + $this->subjectRepeatScore($assignment->section_id, $assignment->subject_id, $day)
            - self::P_SUBJECT_REPEAT; // first lesson of the day is free

        $this->removePlacement($idx);

        return $cost;
    }

    private function slotScore(int $idx): int
    {
        $p = $this->placements[$idx];
        $assignment = $this->assignment($p['assignment_id']);
        $weight = (int) ($assignment->subject->weight ?? 3);
        $score = 0;

        // Required special room already over-booked in this cell (the branch
        // has such rooms — otherwise the lesson simply uses its own classroom).
        $roomType = $assignment->subject->room_type;
        $supply = $this->ctx->roomSupply($roomType);

        if ($roomType !== null && $supply > 0) {
            foreach ($this->runFrom($p['start'], $p['length']) ?? [] as $period) {
                if (($this->roomDemand["{$roomType}-{$p['day']}-{$period}"] ?? 0) > $supply) {
                    $score += self::P_NO_ROOM;
                }
            }
        }

        if ($weight >= 4) {
            $score += ($weight - 3) * self::P_HEAVY_LATE * $this->periodPos[$p['start']];

            $lastPeriod = $this->ctx->periods[count($this->ctx->periods) - 1];
            $endPeriods = $this->runFrom($p['start'], $p['length']) ?? [];

            if ($p['day'] === 5 && in_array($lastPeriod, $endPeriods, true)) {
                $score += self::P_HEAVY_FRIDAY_LAST;
            }
        }

        return $score;
    }

    /** Lessons of (section, subject) already on this day → repeat penalty. */
    private function subjectRepeatScore(int $sectionId, int $subjectId, int $day): int
    {
        $lessons = 0;

        foreach ($this->placements as $p) {
            if ($p['day'] !== $day) {
                continue;
            }
            $a = $this->assignment($p['assignment_id']);

            if ($a->section_id === $sectionId && $a->subject_id === $subjectId) {
                $lessons++;
            }
        }

        return $lessons * self::P_SUBJECT_REPEAT;
    }

    private function teacherDayScore(int $employeeId, int $day): int
    {
        $positions = [];

        foreach ($this->ctx->periods as $period) {
            if (isset($this->teacherBusy["{$employeeId}-{$day}-{$period}"])) {
                $positions[] = $this->periodPos[$period];
            }
        }

        $load = count($positions);

        if ($load === 0) {
            return 0;
        }

        $span = max($positions) - min($positions) + 1;
        $score = ($span - $load) * self::P_TEACHER_GAP
            + $load * $load * self::P_TEACHER_BALANCE;

        // Runs longer than MAX_RUN periods — the burnout guard.
        $run = 0;
        $previous = null;

        foreach ($positions as $pos) {
            $run = ($previous !== null && $pos === $previous + 1) ? $run + 1 : 1;

            if ($run > self::MAX_RUN) {
                $score += self::P_LONG_RUN;
            }

            $previous = $pos;
        }

        return $score;
    }

    private function totalScore(): int
    {
        $score = 0;
        $teacherDays = [];
        $subjectDays = [];

        foreach ($this->placements as $idx => $p) {
            $score += $this->slotScore($idx);
            $a = $this->assignment($p['assignment_id']);

            if ($a->employee_id !== null) {
                $teacherDays[$a->employee_id.'-'.$p['day']] = [$a->employee_id, $p['day']];
            }

            $key = $a->section_id.'-'.$a->subject_id.'-'.$p['day'];
            $subjectDays[$key] = ($subjectDays[$key] ?? 0) + 1;
        }

        foreach ($teacherDays as [$employeeId, $day]) {
            $score += $this->teacherDayScore($employeeId, $day);
        }

        foreach ($subjectDays as $lessons) {
            $score += max(0, $lessons - 1) * self::P_SUBJECT_REPEAT;
        }

        return $score;
    }

    // ─────────────────────────── local search ───────────────────────────

    private function improve(): void
    {
        $movable = array_keys(array_filter($this->placements, fn ($p) => ! $p['locked']));

        if ($movable === []) {
            return;
        }

        $deadline = microtime(true) + self::TIME_BUDGET_SECONDS;
        $iterations = 20000;

        while ($iterations-- > 0 && microtime(true) < $deadline) {
            $idx = $movable[array_rand($movable)];
            $p = $this->placements[$idx];
            $a = $this->assignment($p['assignment_id']);

            $oldCost = $this->costWithout($idx, $p, $a);

            $this->removePlacement($idx);

            $day = $this->ctx->days[array_rand($this->ctx->days)];
            $start = $this->ctx->periods[array_rand($this->ctx->periods)];

            $accepted = false;

            if ($this->fits($p['assignment_id'], $day, $start, $p['length'])) {
                $newCost = $this->placementCost($p['assignment_id'], $day, $start, $p['length']);

                if ($newCost <= $oldCost) {
                    $this->placements[$idx] = ['assignment_id' => $p['assignment_id'], 'day' => $day, 'start' => $start, 'length' => $p['length'], 'locked' => false];
                    $this->occupy($idx);
                    $accepted = true;
                }
            }

            if (! $accepted) {
                $this->placements[$idx] = $p;
                $this->occupy($idx);
            }
        }
    }

    /** Cost of a placement judged as if it were being (re)placed now. */
    private function costWithout(int $idx, array $p, $a): int
    {
        $this->removePlacement($idx);
        $cost = $this->placementCost($p['assignment_id'], $p['day'], $p['start'], $p['length']);
        $this->placements[$idx] = $p;
        $this->occupy($idx);

        return $cost;
    }

    // ─────────────────────────── state ───────────────────────────

    private function addPlacement(int $assignmentId, int $day, int $start, int $length, bool $locked): int
    {
        $this->placements[] = ['assignment_id' => $assignmentId, 'day' => $day, 'start' => $start, 'length' => $length, 'locked' => $locked];
        $idx = array_key_last($this->placements);
        $this->occupy($idx);

        return $idx;
    }

    private function occupy(int $idx): void
    {
        $p = $this->placements[$idx];
        $a = $this->assignment($p['assignment_id']);
        $roomType = $a->subject->room_type;

        foreach ($this->runFrom($p['start'], $p['length']) ?? [] as $period) {
            $this->sectionBusy[$a->section_id."-{$p['day']}-{$period}"] = $idx;

            if ($a->employee_id !== null) {
                $this->teacherBusy[$a->employee_id."-{$p['day']}-{$period}"] = $idx;
            }

            if ($roomType !== null) {
                $key = "{$roomType}-{$p['day']}-{$period}";
                $this->roomDemand[$key] = ($this->roomDemand[$key] ?? 0) + 1;
            }
        }
    }

    private function removePlacement(int $idx): void
    {
        $p = $this->placements[$idx];
        $a = $this->assignment($p['assignment_id']);
        $roomType = $a->subject->room_type;

        foreach ($this->runFrom($p['start'], $p['length']) ?? [] as $period) {
            unset($this->sectionBusy[$a->section_id."-{$p['day']}-{$period}"]);

            if ($a->employee_id !== null) {
                unset($this->teacherBusy[$a->employee_id."-{$p['day']}-{$period}"]);
            }

            if ($roomType !== null) {
                $key = "{$roomType}-{$p['day']}-{$period}";
                $this->roomDemand[$key] = max(0, ($this->roomDemand[$key] ?? 1) - 1);
            }
        }

        unset($this->placements[$idx]);
    }

    private function teacherDayLoad(int $employeeId, int $day): int
    {
        $load = 0;

        foreach ($this->ctx->periods as $period) {
            if (isset($this->teacherBusy["{$employeeId}-{$day}-{$period}"])) {
                $load++;
            }
        }

        return $load;
    }

    private function assignment(int $id)
    {
        return $this->ctx->assignments->get($id);
    }

    // ─────────────────────────── persistence ───────────────────────────

    private function persist(TimetableVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $version->slots()->where('is_locked', false)->delete();

            // Special-room booking: greedy per cell, seeded with the rooms
            // locked slots already hold so they are never double-booked.
            $roomBusy = [];

            foreach ($this->ctx->slots as $slot) {
                if ($slot->is_locked && $slot->room_id !== null) {
                    $roomBusy["{$slot->room_id}-{$slot->day_of_week}-{$slot->period_number}"] = true;
                }
            }

            $rows = [];
            $now = now();

            foreach ($this->placements as $p) {
                if ($p['locked']) {
                    continue;
                }

                $periods = $this->runFrom($p['start'], $p['length']) ?? [];
                $roomId = $this->bookRoom($p['assignment_id'], $p['day'], $periods, $roomBusy);

                foreach ($periods as $period) {
                    $rows[] = [
                        'timetable_version_id' => $version->id,
                        'subject_assignment_id' => $p['assignment_id'],
                        'room_id' => $roomId,
                        'day_of_week' => $p['day'],
                        'period_number' => $period,
                        'is_locked' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                TimetableSlot::insert($chunk);
            }
        });
    }

    /**
     * A room of the subject's required type free for the WHOLE lesson run,
     * or null (own classroom) when none is needed or none is free.
     *
     * @param  list<int>  $periods
     * @param  array<string, bool>  $roomBusy
     */
    private function bookRoom(int $assignmentId, int $day, array $periods, array &$roomBusy): ?int
    {
        $type = $this->assignment($assignmentId)->subject->room_type;

        if ($type === null) {
            return null;
        }

        foreach ($this->ctx->roomsByType[$type] ?? [] as $roomId) {
            $free = true;

            foreach ($periods as $period) {
                if (isset($roomBusy["{$roomId}-{$day}-{$period}"])) {
                    $free = false;
                    break;
                }
            }

            if ($free) {
                foreach ($periods as $period) {
                    $roomBusy["{$roomId}-{$day}-{$period}"] = true;
                }

                return $roomId;
            }
        }

        return null;
    }
}
