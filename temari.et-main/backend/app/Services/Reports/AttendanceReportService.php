<?php

namespace App\Services\Reports;

use App\Enums\EnrollmentStatus;
use App\Models\AttendanceRecord;
use App\Models\Device;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Support\SearchTerm;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics over the daily student register (attendance_records).
 * Every method takes an AttendanceReportQuery whose tenant scope the
 * controller has already authorised — the service only aggregates within it.
 * All heavy lifting is grouped SQL on the (branch_id, date) / (section_id,
 * date) indexes; nothing here loads a record per student per day.
 */
class AttendanceReportService
{
    /**
     * Chronic absenteeism: absent on ≥10% of recorded days (the standard
     * international definition), once enough days are on the books to judge.
     */
    public const CHRONIC_ABSENT_SHARE = 0.10;

    private const MIN_RECORDED_DAYS = 5;

    /**
     * Headline KPIs for the reports dashboard: status mix + rate (with the
     * previous equal-length window for the delta), register coverage, the
     * manual/device split with a per-terminal breakdown, punctuality and the
     * gender split of absences.
     *
     * @return array<string, mixed>
     */
    public function overview(AttendanceReportQuery $query): array
    {
        // toBase(): aggregate rows must not run through the model's enum casts.
        $byStatus = $this->marks($query)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $byStatus->sum();
        $present = (int) $byStatus->get('present', 0);
        $late = (int) $byStatus->get('late', 0);
        $absent = (int) $byStatus->get('absent', 0);

        $headline = $this->marks($query)
            ->toBase()
            ->selectRaw('count(distinct date) as school_days, count(distinct student_id) as students')
            ->first();
        $schoolDays = (int) ($headline->school_days ?? 0);

        $previous = $this->rateFor($query->previousWindow());

        // The register a human or a gate actually produced vs what a full
        // register would hold: active enrollments × marked school days. Uses
        // source-unfiltered marks so a device/manual filter never skews it.
        $recordedAll = $query->source === null && $query->deviceId === null
            ? $total
            : (int) $this->marks($query, ignoreSource: true)->toBase()->count();
        $expected = $this->enrolledCount($query) * $schoolDays;

        $sources = $this->marks($query, ignoreSource: true)
            ->toBase()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $devices = $this->marks($query, ignoreSource: true)
            ->whereNotNull('attendance_records.device_id')
            ->join('devices', 'devices.id', '=', 'attendance_records.device_id')
            ->toBase()
            ->selectRaw(<<<'SQL'
                devices.id, devices.name, devices.location,
                count(*) as marks,
                count(*) filter (where attendance_records.status = 'late') as late
                SQL)
            ->groupBy('devices.id', 'devices.name', 'devices.location')
            ->orderByDesc('marks')
            ->get();

        $arrival = $this->marks($query)
            ->whereNotNull('check_in')
            ->toBase()
            ->selectRaw(<<<'SQL'
                count(*) as with_check_in,
                avg(extract(epoch from check_in)) as avg_check_in,
                avg(extract(epoch from check_in)) filter (where status = 'late') as avg_late_check_in
                SQL)
            ->first();

        $absencesByGender = $this->marks($query)
            ->where('attendance_records.status', 'absent')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->toBase()
            ->selectRaw('students.gender, count(*) as total')
            ->groupBy('students.gender')
            ->pluck('total', 'gender');

        // Per-student rollup → how many are chronically absent / spotless.
        $flags = DB::query()
            ->fromSub(
                $this->marks($query)
                    ->toBase()
                    ->selectRaw(<<<'SQL'
                        student_id,
                        count(*) as recorded,
                        count(*) filter (where status = 'absent') as absent,
                        count(*) filter (where status = 'late') as late
                        SQL)
                    ->groupBy('student_id'),
                'per_student',
            )
            ->selectRaw(
                <<<'SQL'
                count(*) filter (where recorded >= ? and absent::numeric / recorded >= ?) as chronic,
                count(*) filter (where absent = 0 and late = 0 and recorded >= ?) as perfect
                SQL,
                [self::MIN_RECORDED_DAYS, self::CHRONIC_ABSENT_SHARE, max(1, $schoolDays)],
            )
            ->first();

        return [
            'window' => [
                'from' => $query->from,
                'to' => $query->to,
                'school_days' => $schoolDays,
            ],
            'totals' => [
                'marks' => $total,
                'students' => (int) ($headline->students ?? 0),
                'by_status' => $byStatus,
                'attendance_rate' => $total > 0 ? round(($present + $late) / $total * 100, 1) : null,
                'previous_rate' => $previous,
            ],
            'coverage' => [
                'recorded' => $recordedAll,
                'expected' => $expected,
                'rate' => $expected > 0 ? round(min(1, $recordedAll / $expected) * 100, 1) : null,
            ],
            'punctuality' => [
                'late' => $late,
                'on_time_rate' => ($present + $late) > 0
                    ? round($present / ($present + $late) * 100, 1)
                    : null,
                'average_check_in' => $this->secondsToClock($arrival->avg_check_in ?? null),
                'average_late_check_in' => $this->secondsToClock($arrival->avg_late_check_in ?? null),
            ],
            'absences' => [
                'total' => $absent,
                'by_gender' => [
                    'female' => (int) $absencesByGender->get('female', 0),
                    'male' => (int) $absencesByGender->get('male', 0),
                ],
                'chronic_students' => (int) ($flags->chronic ?? 0),
                'perfect_students' => (int) ($flags->perfect ?? 0),
            ],
            'sources' => [
                'manual' => (int) $sources->get('manual', 0),
                'device' => (int) $sources->get('device', 0),
                'devices' => $devices->map(fn (object $d): array => [
                    'id' => (int) $d->id,
                    'name' => $d->name,
                    'location' => $d->location,
                    'marks' => (int) $d->marks,
                    'late' => (int) $d->late,
                ])->values(),
            ],
        ];
    }

    /**
     * Chart series: the day-by-day register (status + source mix), an adaptive
     * league table one level below the current scope (schools → branches →
     * grades → sections), and the arrival-time histogram from check-ins.
     *
     * @return array<string, mixed>
     */
    public function trends(AttendanceReportQuery $query): array
    {
        $daily = $this->marks($query)
            ->toBase()
            ->selectRaw(<<<'SQL'
                date,
                count(*) filter (where status = 'present') as present,
                count(*) filter (where status = 'late') as late,
                count(*) filter (where status = 'absent') as absent,
                count(*) filter (where status = 'excused') as excused,
                count(*) filter (where source = 'device') as device,
                count(*) filter (where source = 'manual') as manual
                SQL)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => CarbonImmutable::parse($row->date)->toDateString(),
                'present' => (int) $row->present,
                'late' => (int) $row->late,
                'absent' => (int) $row->absent,
                'excused' => (int) $row->excused,
                'device' => (int) $row->device,
                'manual' => (int) $row->manual,
            ]);

        // Arrivals in 30-minute buckets — the gate-morning picture.
        $arrivals = $this->marks($query)
            ->whereNotNull('check_in')
            ->toBase()
            ->selectRaw(<<<'SQL'
                (floor(extract(epoch from check_in) / 1800) * 1800)::int as bucket,
                count(*) as total,
                count(*) filter (where status = 'late') as late
                SQL)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn (object $row): array => [
                'time' => gmdate('H:i', (int) $row->bucket),
                'total' => (int) $row->total,
                'late' => (int) $row->late,
            ]);

        [$group, $rows] = $this->breakdown($query);

        return [
            'daily' => $daily,
            'arrivals' => $arrivals,
            'breakdown' => ['group' => $group, 'rows' => $rows],
        ];
    }

    /**
     * The per-student ledger: aggregate counts + rate per student over the
     * window, server-driven (search / sort / flag filter / pagination), each
     * page row enriched with its recent-marks sparkline and current absence
     * streak.
     *
     * @param  array{search?: string|null, flag?: string|null, sort?: string|null, dir?: string|null}  $options
     * @return LengthAwarePaginator<int, mixed>
     */
    public function students(AttendanceReportQuery $query, array $options, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->studentRows($query, $options)->paginate($perPage);
        $items = collect($paginator->items());

        $recent = $this->recentMarks($query, $items->pluck('student_id')->map(fn ($id) => (int) $id)->all());

        $paginator->setCollection($items->map(function (object $row) use ($recent): array {
            /** @var Collection<int, object> $marks newest first */
            $marks = $recent->get((int) $row->student_id) ?? collect();

            $streak = 0;
            foreach ($marks as $mark) {
                if ($mark->status !== 'absent') {
                    break;
                }
                $streak++;
            }

            return $this->studentRow($row) + [
                'absent_streak' => $streak,
                'last_marks' => $marks->take(10)->reverse()->values()
                    ->map(fn (object $mark): array => [
                        'date' => CarbonImmutable::parse($mark->date)->toDateString(),
                        'status' => $mark->status,
                    ]),
            ];
        }));

        return $paginator;
    }

    /**
     * Full (capped) result set for CSV/Excel export — no sparkline pass.
     *
     * @param  array{search?: string|null, flag?: string|null, sort?: string|null, dir?: string|null}  $options
     * @return Collection<int, mixed>
     */
    public function studentsExport(AttendanceReportQuery $query, array $options, int $limit = 2000): Collection
    {
        return $this->studentRows($query, $options)->limit($limit)->get()->map($this->studentRow(...));
    }

    /**
     * Terminals whose marks fall inside the caller's scope — the options list
     * behind the device filter (registrars may lack devices.view, so the
     * report carries its own list).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function deviceOptions(AttendanceReportQuery $query): Collection
    {
        return Device::query()
            ->when($query->schoolId !== null, fn ($q) => $q->where('school_id', $query->schoolId))
            ->when($query->branchId !== null, fn ($q) => $q->where('branch_id', $query->branchId))
            ->whereIn('audience', ['students', 'both'])
            ->orderBy('name')
            ->get(['id', 'name', 'location'])
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ]);
    }

    /**
     * Scoped, filtered marks — the base of every aggregate here.
     * `$ignoreSource` drops the manual/device narrowing for metrics that must
     * describe the whole register (coverage, the source mix itself).
     *
     * @return Builder<AttendanceRecord>
     */
    private function marks(AttendanceReportQuery $query, bool $ignoreSource = false): Builder
    {
        return AttendanceRecord::query()
            ->when($query->schoolId !== null, fn ($q) => $q->where('attendance_records.school_id', $query->schoolId))
            ->when($query->branchId !== null, fn ($q) => $q->where('attendance_records.branch_id', $query->branchId))
            ->when($query->allowedSectionIds !== null, fn ($q) => $q->whereIn('attendance_records.section_id', $query->allowedSectionIds))
            ->whereBetween('attendance_records.date', [$query->from, $query->to])
            ->when($query->gradeLevelId !== null, fn ($q) => $q->whereIn(
                'attendance_records.section_id',
                Section::query()->select('id')->where('grade_level_id', $query->gradeLevelId),
            ))
            ->when($query->sectionId !== null, fn ($q) => $q->where('attendance_records.section_id', $query->sectionId))
            ->when(! $ignoreSource && $query->source !== null, fn ($q) => $q->where('attendance_records.source', $query->source))
            ->when(! $ignoreSource && $query->deviceId !== null, fn ($q) => $q->where('attendance_records.device_id', $query->deviceId));
    }

    private function rateFor(AttendanceReportQuery $query): ?float
    {
        $row = $this->marks($query)
            ->toBase()
            ->selectRaw("count(*) filter (where status in ('present', 'late')) as attended, count(*) as total")
            ->first();

        return ((int) $row->total) > 0
            ? round((int) $row->attended / (int) $row->total * 100, 1)
            : null;
    }

    /** Active enrollments inside the same scope + grade/section filters. */
    private function enrolledCount(AttendanceReportQuery $query): int
    {
        return StudentEnrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->when($query->schoolId !== null, fn ($q) => $q->where('school_id', $query->schoolId))
            ->when($query->branchId !== null, fn ($q) => $q->where('branch_id', $query->branchId))
            ->when($query->allowedSectionIds !== null, fn ($q) => $q->whereIn('section_id', $query->allowedSectionIds))
            ->when($query->gradeLevelId !== null, fn ($q) => $q->where('grade_level_id', $query->gradeLevelId))
            ->when($query->sectionId !== null, fn ($q) => $q->where('section_id', $query->sectionId))
            ->count();
    }

    /**
     * One league-table level below the current scope: platform-wide compares
     * schools, a school compares branches, a branch compares grades, a grade
     * compares its sections.
     *
     * @return array{string, Collection<int, array<string, mixed>>}
     */
    private function breakdown(AttendanceReportQuery $query): array
    {
        $group = match (true) {
            $query->sectionId !== null,
            $query->gradeLevelId !== null => 'section',
            $query->branchId !== null,
            $query->allowedSectionIds !== null => 'grade',
            $query->schoolId !== null => 'branch',
            default => 'school',
        };

        $counts = <<<'SQL'
            count(distinct attendance_records.student_id) as students,
            count(*) as marks,
            count(*) filter (where attendance_records.status = 'present') as present,
            count(*) filter (where attendance_records.status = 'late') as late,
            count(*) filter (where attendance_records.status = 'absent') as absent,
            count(*) filter (where attendance_records.status = 'excused') as excused
            SQL;

        $rows = match ($group) {
            'school' => $this->marks($query)
                ->join('schools', 'schools.id', '=', 'attendance_records.school_id')
                ->toBase()
                ->selectRaw("schools.id, schools.name, {$counts}")
                ->groupBy('schools.id', 'schools.name')
                ->orderBy('schools.name')
                ->get(),
            'branch' => $this->marks($query)
                ->join('branches', 'branches.id', '=', 'attendance_records.branch_id')
                ->toBase()
                ->selectRaw("branches.id, branches.name, {$counts}")
                ->groupBy('branches.id', 'branches.name')
                ->orderBy('branches.name')
                ->get(),
            'grade' => $this->marks($query)
                ->join('sections', 'sections.id', '=', 'attendance_records.section_id')
                ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
                ->toBase()
                ->selectRaw("grade_levels.id, grade_levels.name, grade_levels.sort_order, {$counts}")
                ->groupBy('grade_levels.id', 'grade_levels.name', 'grade_levels.sort_order')
                ->orderBy('grade_levels.sort_order')
                ->get(),
            'section' => $this->marks($query)
                ->join('sections', 'sections.id', '=', 'attendance_records.section_id')
                ->leftJoin('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
                ->toBase()
                ->selectRaw("sections.id, concat(coalesce(grade_levels.name || ' — ', ''), sections.name) as name, {$counts}")
                ->groupBy('sections.id', 'grade_levels.name', 'sections.name')
                ->orderBy('name')
                ->get(),
        };

        return [$group, $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'students' => (int) $row->students,
            'marks' => (int) $row->marks,
            'present' => (int) $row->present,
            'late' => (int) $row->late,
            'absent' => (int) $row->absent,
            'excused' => (int) $row->excused,
            'rate' => (int) $row->marks > 0
                ? round(((int) $row->present + (int) $row->late) / (int) $row->marks * 100, 1)
                : null,
        ])->values()];
    }

    /**
     * The grouped per-student aggregate query (base builder, ready to
     * paginate or export). Grouping by students.id lets PostgreSQL expose the
     * student's own columns directly (functional dependency on the PK).
     *
     * @param  array{search?: string|null, flag?: string|null, sort?: string|null, dir?: string|null}  $options
     */
    private function studentRows(AttendanceReportQuery $query, array $options): \Illuminate\Database\Query\Builder
    {
        $builder = $this->marks($query)
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->leftJoin('sections', 'sections.id', '=', 'attendance_records.section_id')
            ->leftJoin('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->tap(fn ($q) => SearchTerm::apply($q, $options['search'] ?? null, fn ($w, string $n) => $w
                ->where('students.search_text', 'ilike', SearchTerm::contains($n))))
            ->toBase()
            ->selectRaw(<<<'SQL'
                students.id as student_id,
                students.public_id,
                students.first_name,
                students.father_name,
                students.grandfather_name,
                students.gender,
                max(sections.name) as section_name,
                max(grade_levels.name) as grade_name,
                count(*) as recorded,
                count(*) filter (where attendance_records.status = 'present') as present,
                count(*) filter (where attendance_records.status = 'late') as late,
                count(*) filter (where attendance_records.status = 'absent') as absent,
                count(*) filter (where attendance_records.status = 'excused') as excused
                SQL)
            ->groupBy('students.id');

        $builder = match ($options['flag'] ?? null) {
            'chronic' => $builder->havingRaw(
                'count(*) >= ? and (count(*) filter (where attendance_records.status = \'absent\'))::numeric / count(*) >= ?',
                [self::MIN_RECORDED_DAYS, self::CHRONIC_ABSENT_SHARE],
            ),
            'perfect' => $builder->havingRaw(
                "count(*) filter (where attendance_records.status = 'absent') = 0 and count(*) filter (where attendance_records.status = 'late') = 0",
            ),
            'frequent_late' => $builder->havingRaw(
                "count(*) filter (where attendance_records.status = 'late') >= 3",
            ),
            default => $builder,
        };

        // Whitelisted sort → aggregate expression; a stale client falls back
        // to name so nothing user-supplied ever reaches orderByRaw.
        $sorts = [
            'name' => 'students.first_name',
            'recorded' => 'count(*)',
            'present' => "count(*) filter (where attendance_records.status = 'present')",
            'late' => "count(*) filter (where attendance_records.status = 'late')",
            'absent' => "count(*) filter (where attendance_records.status = 'absent')",
            'excused' => "count(*) filter (where attendance_records.status = 'excused')",
            'rate' => "(count(*) filter (where attendance_records.status in ('present', 'late')))::numeric / count(*)",
        ];
        $sort = $sorts[$options['sort'] ?? ''] ?? $sorts['name'];
        $dir = ($options['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $builder
            ->orderByRaw("{$sort} {$dir}")
            ->orderBy('students.first_name');
    }

    /**
     * @return array<string, mixed>
     */
    private function studentRow(object $row): array
    {
        $recorded = (int) $row->recorded;
        $present = (int) $row->present;
        $late = (int) $row->late;

        return [
            'student_id' => (int) $row->student_id,
            'public_id' => $row->public_id,
            'name' => trim(implode(' ', array_filter([
                $row->first_name, $row->father_name, $row->grandfather_name,
            ]))),
            'gender' => $row->gender,
            'section' => $row->section_name,
            'grade' => $row->grade_name,
            'recorded' => $recorded,
            'present' => $present,
            'late' => $late,
            'absent' => (int) $row->absent,
            'excused' => (int) $row->excused,
            'attendance_rate' => $recorded > 0 ? round(($present + $late) / $recorded * 100, 1) : null,
        ];
    }

    /**
     * Second pass for the visible page only: each student's marks inside the
     * window, newest first — feeds the chronological sparkline and the
     * CURRENT run of absences. Reads the source-unfiltered register — a
     * device filter should never make a student look absent-streaked.
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, Collection<int, object>>
     */
    private function recentMarks(AttendanceReportQuery $query, array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return $this->marks($query, ignoreSource: true)
            ->whereIn('attendance_records.student_id', $studentIds)
            ->toBase()
            ->select('student_id', 'date', 'status')
            ->orderByDesc('date')
            ->get()
            ->groupBy(fn (object $row) => (int) $row->student_id);
    }

    /** Seconds-past-midnight (PG epoch avg) → "HH:MM", null-safe. */
    private function secondsToClock(mixed $seconds): ?string
    {
        return $seconds === null ? null : gmdate('H:i', (int) round((float) $seconds));
    }
}
