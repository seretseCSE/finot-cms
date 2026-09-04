<?php

namespace App\Actions;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\SchoolProgram;
use App\Models\Term;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

use function app;

/**
 * Creates or updates a branch's academic year. At most one year per branch is
 * ACTIVE (the operating year) — activating one demotes the previous active year
 * to completed. On creation it provisions the requested number of semesters
 * (1–5, default 2 per the Ethiopian standard) on the branch's Regular program.
 */
class SaveAcademicYearAction
{
    /**
     * @param  array{
     *     name: string,
     *     starts_on?: ?string,
     *     ends_on?: ?string,
     *     status?: ?string,
     *     terms_count?: int,
     *     is_active?: bool,
     * }  $data
     */
    public function execute(Branch $branch, array $data, ?AcademicYear $year = null): AcademicYear
    {
        return DB::transaction(function () use ($branch, $data, $year): AcademicYear {
            $status = isset($data['status'])
                ? AcademicYearStatus::from($data['status'])
                : ($year?->status ?? AcademicYearStatus::Planned);

            $attributes = [
                'school_id' => $branch->school_id,
                'name' => $data['name'],
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $status,
                'is_active' => $data['is_active'] ?? $year?->is_active ?? true,
            ];

            if ($year === null) {
                $year = $branch->academicYears()->create($attributes);
                $this->seedSemesters(
                    $year,
                    max(1, min(5, (int) ($data['terms_count'] ?? 2))),
                    (bool) ($data['auto_generate_assignments'] ?? false),
                );
            } else {
                $year->update($attributes);
            }

            if ($status === AcademicYearStatus::Active) {
                self::demoteOtherActiveYears($year);
            }

            return $year->load('terms.program');
        });
    }

    /** Only one operating year per branch: previous active years complete. */
    public static function demoteOtherActiveYears(AcademicYear $year): void
    {
        AcademicYear::query()
            ->where('branch_id', $year->branch_id)
            ->whereKeyNot($year->id)
            ->where('status', AcademicYearStatus::Active->value)
            ->update(['status' => AcademicYearStatus::Completed->value]);
    }

    private function seedSemesters(AcademicYear $year, int $count, bool $autoGenerateAssignments = false): void
    {
        // Seed onto the branch's first program (branches choose their own set
        // now — Regular is only the fallback for programless branches).
        $program = SchoolProgram::query()
            ->where('branch_id', $year->branch_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first() ?? SchoolProgram::defaultFor($year->branch);

        $windows = self::termWindows($year->starts_on?->toDateString(), $year->ends_on?->toDateString(), $count);
        $hours = self::inheritedClassHours($year->branch_id);

        foreach (range(1, $count) as $sequence) {
            $term = $year->terms()->create([
                'school_id' => $year->school_id,
                'branch_id' => $year->branch_id,
                'school_program_id' => $program->id,
                'name' => "Semester {$sequence}",
                'sequence' => $sequence,
                'starts_on' => $windows[$sequence - 1]['starts_on'] ?? null,
                'ends_on' => $windows[$sequence - 1]['ends_on'] ?? null,
                'class_starts_at' => $hours['class_starts_at'],
                'class_ends_at' => $hours['class_ends_at'],
                'period_minutes' => $hours['period_minutes'],
                'is_current' => false,
                'is_active' => true,
            ]);

            // Opt-in only (checkbox, default off): pre-build each semester's
            // teaching grid from the curriculum + teacher capabilities.
            if ($autoGenerateAssignments) {
                app(GenerateTermAssignmentsAction::class)->execute($term);
            }
        }
    }

    /**
     * Split the year window into $count contiguous semester windows. When the
     * window is whole Ethiopian months (the normal Meskerem 1 – Sene 30 year),
     * split on MONTH boundaries, longer terms first — two semesters over ten
     * months land exactly on the national convention: Meskerem–Tir and
     * Yekatit–Sene. Anything unaligned falls back to an even split by days.
     *
     * @return list<array{starts_on: string, ends_on: string}>
     */
    public static function termWindows(?string $startsOn, ?string $endsOn, int $count): array
    {
        if ($startsOn === null || $endsOn === null || $count < 1 || $endsOn < $startsOn) {
            return [];
        }

        $start = CarbonImmutable::parse($startsOn);
        $end = CarbonImmutable::parse($endsOn);

        $from = EthiopianDate::fromGregorian($start);
        $to = EthiopianDate::fromGregorian($end);

        // Month-aligned window (Pagume excluded — it is 5–6 days, not a month).
        $monthsSpanned = ($to['year'] * 13 + $to['month']) - ($from['year'] * 13 + $from['month']) + 1;
        $aligned = $from['day'] === 1
            && $to['day'] === EthiopianDate::daysInMonth($to['year'], $to['month'])
            && $from['month'] <= 12 && $to['month'] <= 12
            && $from['year'] === $to['year']
            && $monthsSpanned >= $count;

        if ($aligned) {
            $base = intdiv($monthsSpanned, $count);
            $extra = $monthsSpanned % $count;
            $windows = [];
            $month = $from['month'];

            foreach (range(1, $count) as $i) {
                $length = $base + ($i <= $extra ? 1 : 0);
                $last = $month + $length - 1;
                $windows[] = [
                    'starts_on' => EthiopianDate::monthStart($from['year'], $month)->toDateString(),
                    'ends_on' => EthiopianDate::monthEnd($from['year'], $last)->toDateString(),
                ];
                $month = $last + 1;
            }

            return $windows;
        }

        // Fallback: even split by days, remainder spread from the front.
        $totalDays = (int) $start->diffInDays($end) + 1;
        $base = intdiv($totalDays, $count);
        $extra = $totalDays % $count;
        $windows = [];
        $cursor = $start;

        foreach (range(1, $count) as $i) {
            $length = $base + ($i <= $extra ? 1 : 0);
            $windows[] = [
                'starts_on' => $cursor->toDateString(),
                'ends_on' => $cursor->addDays($length - 1)->toDateString(),
            ];
            $cursor = $cursor->addDays($length);
        }

        return $windows;
    }

    /**
     * Class hours for a new year's semesters: inherit from the branch's most
     * recent term that has them (the running bell schedule), else the standard
     * Ethiopian school day — 8:30 start, seven 45' periods + break + lunch.
     *
     * @return array{class_starts_at: ?string, class_ends_at: ?string, period_minutes: int}
     */
    private static function inheritedClassHours(int $branchId): array
    {
        $previous = Term::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('class_starts_at')
            ->orderByDesc('id')
            ->first();

        if ($previous !== null) {
            return [
                'class_starts_at' => substr((string) $previous->class_starts_at, 0, 5),
                'class_ends_at' => $previous->class_ends_at !== null
                    ? substr((string) $previous->class_ends_at, 0, 5)
                    : null,
                'period_minutes' => $previous->period_minutes ?? 45,
            ];
        }

        return ['class_starts_at' => '08:30', 'class_ends_at' => '14:45', 'period_minutes' => 45];
    }
}
