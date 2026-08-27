<?php

namespace App\Services\Timetable;

use App\Models\Room;
use App\Models\SubjectAssignment;
use App\Models\TeacherAvailability;
use App\Models\TermPeriod;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use Illuminate\Support\Collection;

/**
 * Everything the solver and the validator need about one timetable version,
 * loaded once: the bell schedule's class periods (with adjacency — a double
 * period must not straddle a break), the term's activities (subject
 * assignments), teacher unavailability windows and the current slots.
 */
class TimetableContext
{
    /** @var list<int> ordered class period numbers */
    public array $periods = [];

    /** @var array<int, bool> "p1*100+p2" style adjacency of consecutive class periods */
    public array $adjacent = [];

    /** @var list<int> */
    public array $days = [];

    /** @var Collection<int, SubjectAssignment> keyed by id */
    public Collection $assignments;

    /** @var array<int, list<TeacherAvailability>> employee_id → windows */
    public array $unavailability = [];

    /** @var Collection<int, TimetableSlot> */
    public Collection $slots;

    /** @var array<string, list<int>> room type → active room ids of the branch */
    public array $roomsByType = [];

    public function __construct(public readonly TimetableVersion $version)
    {
        $this->days = array_values(array_map('intval', $version->days ?? [1, 2, 3, 4, 5]));

        $bell = TermPeriod::query()
            ->where('term_id', $version->term_id)
            ->orderBy('sequence')
            ->get();

        $previousWasClass = false;
        $previousNumber = null;

        foreach ($bell as $row) {
            if ($row->type === 'class' && $row->period_number !== null) {
                $number = (int) $row->period_number;
                $this->periods[] = $number;

                if ($previousWasClass && $previousNumber !== null) {
                    $this->adjacent[$previousNumber * 100 + $number] = true;
                }

                $previousWasClass = true;
                $previousNumber = $number;
            } else {
                $previousWasClass = false;
            }
        }

        $this->assignments = SubjectAssignment::query()
            ->where('term_id', $version->term_id)
            ->where('branch_id', $version->branch_id)
            ->where('is_active', true)
            ->where('periods_per_week', '>', 0)
            ->with(['subject:id,code,name,weight,room_type', 'section:id,name,grade_level_id', 'section.gradeLevel:id,name'])
            ->get()
            ->keyBy('id');

        $employeeIds = $this->assignments->pluck('employee_id')->filter()->unique();

        $this->unavailability = TeacherAvailability::query()
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->all())
            ->all();

        $this->slots = $version->slots()->get();

        $this->roomsByType = Room::query()
            ->where('branch_id', $version->branch_id)
            ->where('is_active', true)
            ->get(['id', 'type'])
            ->groupBy('type')
            ->map(fn ($rooms) => $rooms->pluck('id')->all())
            ->all();
    }

    /** Human label for an assignment's section: "Grade 1 — A". */
    public static function sectionLabel(?object $section): ?string
    {
        if ($section === null) {
            return null;
        }

        $grade = $section->gradeLevel?->name;

        return $grade !== null ? "{$grade} — {$section->name}" : $section->name;
    }

    /** How many bookable rooms of a type the branch has. */
    public function roomSupply(?string $type): int
    {
        return $type === null ? 0 : count($this->roomsByType[$type] ?? []);
    }

    public function periodExists(int $period): bool
    {
        return in_array($period, $this->periods, true);
    }

    public function dayExists(int $day): bool
    {
        return in_array($day, $this->days, true);
    }

    /** Is period $b immediately after $a with no break between? */
    public function isAdjacent(int $a, int $b): bool
    {
        return $this->adjacent[$a * 100 + $b] ?? false;
    }

    public function teacherBlocked(?int $employeeId, int $day, int $period): bool
    {
        if ($employeeId === null) {
            return false;
        }

        foreach ($this->unavailability[$employeeId] ?? [] as $window) {
            if ($window->blocks($day, $period)) {
                return true;
            }
        }

        return false;
    }
}
