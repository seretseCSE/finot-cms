<?php

namespace App\Services\LessonPlans;

use App\Enums\LessonCoverage;
use App\Enums\LessonPlanStatus;
use App\Models\AnnualLessonPlan;
use App\Models\AnnualPlanUnit;
use App\Models\DailyLessonPlan;
use App\Models\DailyPlanDelivery;
use App\Models\WeeklyLessonPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The pacing math behind lesson-plan accountability, in one place:
 *
 *  - `summary()` — planned vs covered vs expected-by-today periods for one
 *    annual plan (drives the plan header and the director's dashboard);
 *  - `carryover()` — the previous week's uncovered daily plans (drives BOTH
 *    the submission gate — no justification, no next week — and the
 *    composer's carry-forward suggestions);
 *  - `bulkSummaries()` — the same numbers for a page of plans in two grouped
 *    queries instead of 2×N.
 *
 * A "period" of coverage = one delivery (one section sitting): covered
 * counts 1, partial ½. Coverage is normalised per SECTION — planned periods
 * in the annual grid are per section, so covered periods divide by the
 * number of distinct sections the plan delivers to.
 *
 * "Expected" prorates each unit's planned periods linearly across its date
 * window, so a teacher mid-chapter is not counted behind.
 */
class LessonPlanPacing
{
    /**
     * @return array{planned_periods: int, covered_periods: float, expected_periods: float,
     *   lag_periods: float, units_total: int, units_done: int, progress_percent: int}
     */
    public function summary(AnnualLessonPlan $plan, ?CarbonImmutable $asOf = null): array
    {
        return $this->bulkSummaries([$plan->id], $asOf)[$plan->id];
    }

    /**
     * Pacing summaries for many plans at once, keyed by plan id.
     *
     * @param  list<int>  $planIds
     * @return array<int, array<string, int|float>>
     */
    public function bulkSummaries(array $planIds, ?CarbonImmutable $asOf = null): array
    {
        if ($planIds === []) {
            return [];
        }

        $asOf ??= CarbonImmutable::today();

        $units = AnnualPlanUnit::query()
            ->whereIn('annual_lesson_plan_id', $planIds)
            ->get(['id', 'annual_lesson_plan_id', 'starts_on', 'ends_on', 'planned_periods'])
            ->groupBy('annual_lesson_plan_id');

        // covered/partial sittings per plan, in one grouped query.
        $covered = DailyPlanDelivery::query()
            ->join('daily_lesson_plans', 'daily_lesson_plans.id', '=', 'daily_plan_deliveries.daily_lesson_plan_id')
            ->join('weekly_lesson_plans', 'weekly_lesson_plans.id', '=', 'daily_lesson_plans.weekly_lesson_plan_id')
            ->whereIn('weekly_lesson_plans.annual_lesson_plan_id', $planIds)
            ->whereNull('weekly_lesson_plans.deleted_at')
            ->whereIn('daily_plan_deliveries.coverage', [LessonCoverage::Covered->value, LessonCoverage::Partial->value])
            ->groupBy('weekly_lesson_plans.annual_lesson_plan_id', 'daily_plan_deliveries.coverage')
            ->selectRaw(
                'weekly_lesson_plans.annual_lesson_plan_id as plan_id, daily_plan_deliveries.coverage,'
                .' COUNT(*) as total, COUNT(DISTINCT daily_plan_deliveries.section_id) as sections'
            )
            ->get()
            ->groupBy('plan_id');

        $out = [];
        foreach ($planIds as $id) {
            $coveredPeriods = 0.0;
            $sections = 1;
            foreach ($covered->get($id, collect()) as $row) {
                $coveredPeriods += $row->coverage === LessonCoverage::Partial->value
                    ? (float) $row->total / 2
                    : (float) $row->total;
                $sections = max($sections, (int) $row->sections);
            }

            $out[$id] = $this->compute($units->get($id, collect()), $coveredPeriods / $sections, $asOf);
        }

        return $out;
    }

    /**
     * The uncovered daily plans of the LAST weekly plan before the given
     * week — what the pacing gate blocks on and what the composer offers to
     * carry forward. Only approved/submitted weeks count: a draft the
     * teacher never filed cannot hold their next week hostage.
     *
     * @return array{week: ?WeeklyLessonPlan, lessons: Collection<int, DailyLessonPlan>}
     */
    public function carryover(AnnualLessonPlan $plan, CarbonImmutable $weekStart): array
    {
        $previous = $plan->weeklyPlans()
            ->where('week_starts_on', '<', $weekStart->toDateString())
            ->whereIn('status', [LessonPlanStatus::Submitted->value, LessonPlanStatus::Approved->value])
            ->reorder()->orderByDesc('week_starts_on')
            ->first();

        if ($previous === null) {
            return ['week' => null, 'lessons' => collect()];
        }

        $uncovered = $previous->dailyPlans()
            ->whereHas('deliveries', fn ($q) => $q->whereIn('coverage', [
                LessonCoverage::Pending->value, LessonCoverage::Partial->value, LessonCoverage::Missed->value,
            ]))
            ->with(['unit:id,title,sequence', 'deliveries.section:id,name'])
            ->get();

        return ['week' => $previous, 'lessons' => $uncovered];
    }

    /** Normalize any date to its Monday — every week key in the module. */
    public static function weekStart(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY);
    }

    /**
     * @param  Collection<int, AnnualPlanUnit>  $units
     */
    private function compute(Collection $units, float $coveredPeriods, CarbonImmutable $asOf): array
    {
        $planned = (int) $units->sum('planned_periods');
        $expected = 0.0;
        $unitsDone = 0;

        foreach ($units as $unit) {
            if ($unit->starts_on === null || $unit->ends_on === null || $unit->planned_periods === 0) {
                continue;
            }

            if ($asOf->gte($unit->ends_on)) {
                $expected += $unit->planned_periods;
                $unitsDone++;

                continue;
            }

            if ($asOf->lte($unit->starts_on)) {
                continue;
            }

            $span = max(1, $unit->starts_on->diffInDays($unit->ends_on));
            $elapsed = $unit->starts_on->diffInDays($asOf);
            $expected += $unit->planned_periods * min(1, $elapsed / $span);
        }

        return [
            'planned_periods' => $planned,
            'covered_periods' => round($coveredPeriods, 1),
            'expected_periods' => round($expected, 1),
            'lag_periods' => round(max(0, $expected - $coveredPeriods), 1),
            'units_total' => $units->count(),
            'units_done' => $unitsDone,
            'progress_percent' => $planned > 0 ? (int) round(min(100, $coveredPeriods / $planned * 100)) : 0,
        ];
    }
}
