<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\ConcessionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\MarklistStatus;
use App\Enums\PaymentVerificationStatus;
use App\Enums\SubmissionStatus;
use App\Enums\TransferRequestStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeAttendanceRecord;
use App\Models\Expense;
use App\Models\FeeConcession;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Marklist;
use App\Models\Payment;
use App\Models\PaymentVerification;
use App\Models\School;
use App\Models\SectionHomeroom;
use App\Models\StudentEnrollment;
use App\Models\StudentTransferRequest;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\Ethiopia;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The role-adaptive staff dashboard: one aggregated payload per request,
 * assembled block-by-block from what the caller's permissions allow in the
 * validated context. Scope-level blocks (KPIs, charts, queues) are cached per
 * scope+day so a busy school morning never recomputes them per user; the
 * personal teacher block is cached per user with a short TTL because it
 * carries live "marked today" flags.
 *
 * Scope discipline: every query here is anchored on the school_id/branch_id
 * the CONTROLLER resolved from validated context — the service never widens.
 */
class DashboardService
{
    /** Seconds a scope-level block stays cached. */
    private const TTL = 180;

    /** Seconds the personal (teacher) block stays cached. */
    private const PERSONAL_TTL = 60;

    /**
     * Blocks every caller gets: today's date (Gregorian + Ethiopian) and the
     * branch's current term with its progress — the "where are we in the
     * year" strip at the top of the dashboard.
     *
     * @return array<string, mixed>
     */
    public function context(?int $branchId): array
    {
        $today = CarbonImmutable::parse(Ethiopia::today());
        ['year' => $ey, 'month' => $em, 'day' => $ed] = EthiopianDate::fromGregorian($today);

        $term = null;
        if ($branchId !== null) {
            $current = Term::query()
                ->where('branch_id', $branchId)
                ->where('is_current', true)
                ->with('academicYear:id,name')
                ->first();

            if ($current !== null) {
                $start = $current->starts_on ? CarbonImmutable::parse($current->starts_on) : null;
                $end = $current->ends_on ? CarbonImmutable::parse($current->ends_on) : null;

                $term = [
                    'id' => $current->id,
                    'name' => $current->name,
                    'year_name' => $current->academicYear?->name,
                    'status' => $current->status instanceof \BackedEnum ? $current->status->value : $current->status,
                    'starts_on' => $start?->toDateString(),
                    'ends_on' => $end?->toDateString(),
                    'week' => $start !== null && $today->gte($start)
                        ? intdiv($start->diffInDays($today), 7) + 1
                        : null,
                    'days_left' => $end !== null && $today->lte($end) ? $today->diffInDays($end) : null,
                    'progress' => $start !== null && $end !== null && $end->gt($start)
                        ? (int) round(min(100, max(0, $start->diffInDays($today) / $start->diffInDays($end) * 100)))
                        : null,
                ];
            }
        }

        return [
            'today' => $today->toDateString(),
            'ethiopian' => [
                'year' => $ey,
                'month' => $em,
                'day' => $ed,
                'label' => EthiopianDate::monthLabel($ey, $em),
            ],
            'term' => $term,
        ];
    }

    /**
     * Student attendance vitals: today's register (marked vs enrolled, status
     * split, rate) and the last 7 marked school days as a stacked series.
     * Rate = (present + late) / marks — same definition as the reports pages.
     *
     * @return array<string, mixed>
     */
    public function attendance(?int $schoolId, ?int $branchId): array
    {
        return $this->rememberScoped('attendance', $schoolId, $branchId, function () use ($schoolId, $branchId): array {
            $today = Ethiopia::today();

            $byStatus = $this->scoped(AttendanceRecord::query(), $schoolId, $branchId)
                ->where('date', $today)
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $marked = (int) $byStatus->sum();
            $present = (int) $byStatus->get('present', 0);
            $late = (int) $byStatus->get('late', 0);

            $enrolled = $this->scoped(StudentEnrollment::query(), $schoolId, $branchId)
                ->where('status', EnrollmentStatus::Active->value)
                ->count();

            // Last 7 school days that actually have marks, within a 21-day
            // lookback so holiday-heavy stretches still fill the chart.
            $week = $this->scoped(AttendanceRecord::query(), $schoolId, $branchId)
                ->where('date', '>=', CarbonImmutable::parse($today)->subDays(21)->toDateString())
                ->where('date', '<=', $today)
                ->toBase()
                ->selectRaw(<<<'SQL'
                    date,
                    count(*) filter (where status = 'present') as present,
                    count(*) filter (where status = 'late') as late,
                    count(*) filter (where status = 'absent') as absent,
                    count(*) filter (where status = 'excused') as excused
                    SQL)
                ->groupBy('date')
                ->orderByDesc('date')
                ->limit(7)
                ->get()
                ->reverse()
                ->map(fn (object $row): array => [
                    'date' => $row->date,
                    'present' => (int) $row->present,
                    'late' => (int) $row->late,
                    'absent' => (int) $row->absent,
                    'excused' => (int) $row->excused,
                ])
                ->values()
                ->all();

            return [
                'today' => [
                    'marked' => $marked,
                    'enrolled' => $enrolled,
                    'present' => $present,
                    'late' => $late,
                    'absent' => (int) $byStatus->get('absent', 0),
                    'excused' => (int) $byStatus->get('excused', 0),
                    'rate' => $marked > 0 ? round(($present + $late) / $marked * 100, 1) : null,
                ],
                'week' => $week,
            ];
        });
    }

    /**
     * Money vitals: collections this Ethiopian month, the open receivables
     * position, and a 6-Ethiopian-month collection trend. Amounts are decimal
     * strings like every finance endpoint.
     *
     * @return array<string, mixed>
     */
    public function finance(?int $schoolId, ?int $branchId): array
    {
        return $this->rememberScoped('finance', $schoolId, $branchId, function () use ($schoolId, $branchId): array {
            $today = CarbonImmutable::parse(Ethiopia::today());
            ['year' => $ey, 'month' => $em] = EthiopianDate::fromGregorian($today);

            // Six Ethiopian months ending in the current one. Daily sums are
            // bucketed in PHP — one indexed query instead of six.
            $windows = [];
            for ($i = 5; $i >= 0; $i--) {
                ['year' => $y, 'month' => $m] = EthiopianDate::addMonths($ey, $em, -$i);
                $windows[] = [
                    'year' => $y,
                    'month' => $m,
                    'label' => EthiopianDate::monthLabel($y, $m),
                    'from' => EthiopianDate::monthStart($y, $m)->toDateString(),
                    'to' => EthiopianDate::monthEnd($y, $m)->toDateString(),
                ];
            }

            $daily = $this->scoped(Payment::query(), $schoolId, $branchId)
                ->whereBetween('paid_at', [$windows[0]['from'], $windows[5]['to']])
                ->toBase()
                ->selectRaw('paid_at, sum(amount) as total, count(*) as payments')
                ->groupBy('paid_at')
                ->get();

            $trend = array_map(function (array $window) use ($daily): array {
                $slice = $daily->filter(fn (object $row): bool => $row->paid_at >= $window['from'] && $row->paid_at <= $window['to']);

                return [
                    'ec_year' => $window['year'],
                    'ec_month' => $window['month'],
                    'month' => $window['label'],
                    'collected' => number_format((float) $slice->sum('total'), 2, '.', ''),
                    'payments' => (int) $slice->sum('payments'),
                ];
            }, $windows);

            $current = end($trend);

            $due = Invoice::totalDueSql();
            $openRow = $this->scoped(Invoice::query(), $schoolId, $branchId)
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->toBase()
                ->selectRaw(
                    <<<SQL
                    count(distinct student_id) as students,
                    coalesce(sum(({$due}) - amount_paid), 0) as balance,
                    coalesce(sum(case when due_date < ? then ({$due}) - amount_paid else 0 end), 0) as overdue
                    SQL,
                    [$today->toDateString()],
                )
                ->first();

            return [
                'month' => [
                    'ec_year' => $current['ec_year'],
                    'ec_month' => $current['ec_month'],
                    'label' => $current['month'],
                    'collected' => $current['collected'],
                    'payments' => $current['payments'],
                ],
                'receivables' => [
                    'balance' => number_format((float) ($openRow->balance ?? 0), 2, '.', ''),
                    'overdue' => number_format((float) ($openRow->overdue ?? 0), 2, '.', ''),
                    'students' => (int) ($openRow->students ?? 0),
                ],
                'trend' => $trend,
            ];
        });
    }

    /**
     * Today's employee register: who is in, late, absent or on approved leave.
     *
     * @return array<string, mixed>
     */
    public function staffToday(?int $schoolId, ?int $branchId): array
    {
        return $this->rememberScoped('staff', $schoolId, $branchId, function () use ($schoolId, $branchId): array {
            $today = Ethiopia::today();

            $byStatus = $this->scoped(EmployeeAttendanceRecord::query(), $schoolId, $branchId)
                ->where('date', $today)
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $onLeave = $this->scoped(LeaveRequest::query(), $schoolId, $branchId)
                ->approvedOverlapping($today, $today)
                ->distinct()
                ->count('employee_id');

            $total = $this->scoped(Employee::query(), $schoolId, $branchId)
                ->where('is_active', true)
                ->count();

            return [
                'total' => $total,
                'present' => (int) $byStatus->get('present', 0) + (int) $byStatus->get('half_day', 0),
                'late' => (int) $byStatus->get('late', 0),
                'absent' => (int) $byStatus->get('absent', 0),
                'on_leave' => $onLeave,
                'marked' => (int) $byStatus->sum(),
            ];
        });
    }

    /**
     * The "needs your attention" queue: one row per approval/verification pile
     * the caller is allowed to act on, zero-count rows included so the client
     * can celebrate an empty desk. Permission gating happens in the
     * controller — this only counts.
     *
     * @param  list<string>  $keys  which queue items to compute
     * @return list<array{key: string, count: int}>
     */
    public function queue(array $keys, ?int $schoolId, ?int $branchId): array
    {
        $counts = $this->rememberScoped('queue:'.implode(',', $keys), $schoolId, $branchId, function () use ($keys, $schoolId, $branchId): array {
            $rows = [];

            foreach ($keys as $key) {
                $rows[$key] = match ($key) {
                    'pending_enrollments' => $this->scoped(StudentEnrollment::query(), $schoolId, $branchId)
                        ->where('status', EnrollmentStatus::Pending->value)
                        ->count(),
                    'payment_verifications' => PaymentVerification::query()
                        ->where('status', PaymentVerificationStatus::NeedsReview->value)
                        ->whereHas('invoice', fn (Builder $q) => $this->scoped($q, $schoolId, $branchId))
                        ->count(),
                    'expenses_pending' => $this->scoped(Expense::query(), $schoolId, $branchId)
                        ->where('status', 'pending')
                        ->count(),
                    'leave_pending' => $this->scoped(LeaveRequest::query(), $schoolId, $branchId)
                        ->where('status', LeaveRequestStatus::Pending->value)
                        ->count(),
                    'transfers_incoming' => StudentTransferRequest::query()
                        ->where('status', TransferRequestStatus::Requested->value)
                        ->where(function (Builder $q) use ($schoolId, $branchId): void {
                            // The sending side decides, so surface requests
                            // FROM this scope (plus ones we initiated).
                            $q->where(fn (Builder $s) => $this->scopedColumns($s, 'from_school_id', 'from_branch_id', $schoolId, $branchId))
                                ->orWhere(fn (Builder $s) => $this->scopedColumns($s, 'to_school_id', 'to_branch_id', $schoolId, $branchId));
                        })
                        ->count(),
                    'marklists_submitted' => $this->scoped(Marklist::query(), $schoolId, $branchId)
                        ->where('status', MarklistStatus::Submitted->value)
                        ->count(),
                    'concessions_pending' => $this->scoped(FeeConcession::query(), $schoolId, $branchId)
                        ->where('status', ConcessionStatus::Pending->value)
                        ->count(),
                    default => 0,
                };
            }

            return $rows;
        });

        return collect($counts)
            ->map(fn (int $count, string $key): array => ['key' => $key, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Per-branch comparison for the school-wide workspace: enrollment,
     * today's attendance rate and this Ethiopian month's collections, one row
     * per branch so a principal spots the outlier at a glance.
     *
     * @return list<array<string, mixed>>
     */
    public function branchComparison(School $school): array
    {
        return Cache::remember(
            "dashboard:v1:branch-compare:{$school->id}:".Ethiopia::today(),
            self::TTL,
            function () use ($school): array {
                $today = CarbonImmutable::parse(Ethiopia::today());
                ['year' => $ey, 'month' => $em] = EthiopianDate::fromGregorian($today);
                $monthFrom = EthiopianDate::monthStart($ey, $em)->toDateString();
                $monthTo = EthiopianDate::monthEnd($ey, $em)->toDateString();

                $students = StudentEnrollment::query()
                    ->where('school_id', $school->id)
                    ->where('status', EnrollmentStatus::Active->value)
                    ->toBase()
                    ->selectRaw('branch_id, count(*) as total')
                    ->groupBy('branch_id')
                    ->pluck('total', 'branch_id');

                $attendance = AttendanceRecord::query()
                    ->where('school_id', $school->id)
                    ->where('date', $today->toDateString())
                    ->toBase()
                    ->selectRaw(<<<'SQL'
                        branch_id,
                        count(*) as marked,
                        count(*) filter (where status in ('present', 'late')) as attending
                        SQL)
                    ->groupBy('branch_id')
                    ->get()
                    ->keyBy('branch_id');

                $collections = Payment::query()
                    ->where('school_id', $school->id)
                    ->whereBetween('paid_at', [$monthFrom, $monthTo])
                    ->toBase()
                    ->selectRaw('branch_id, sum(amount) as total')
                    ->groupBy('branch_id')
                    ->pluck('total', 'branch_id');

                return $school->branches()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(function (Branch $branch) use ($students, $attendance, $collections): array {
                        $marks = $attendance->get($branch->id);

                        return [
                            'id' => $branch->id,
                            'name' => $branch->name,
                            'code' => $branch->code,
                            'students' => (int) ($students[$branch->id] ?? 0),
                            'attendance_rate' => $marks !== null && (int) $marks->marked > 0
                                ? round((int) $marks->attending / (int) $marks->marked * 100, 1)
                                : null,
                            'attendance_marked' => (int) ($marks->marked ?? 0),
                            'collected_month' => number_format((float) ($collections[$branch->id] ?? 0), 2, '.', ''),
                        ];
                    })
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * Platform vitals for Temari.et staff in the global workspace.
     *
     * @return array<string, mixed>
     */
    public function platform(): array
    {
        return Cache::remember('dashboard:v1:platform:'.Ethiopia::today(), self::TTL, function (): array {
            $recent = School::query()
                ->withCount(['branches'])
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'created_at'])
                ->map(fn (School $school): array => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'branches' => (int) $school->branches_count,
                    'created_at' => $school->created_at?->toDateString(),
                ])
                ->all();

            return [
                'schools' => School::query()->count(),
                'branches' => Branch::query()->count(),
                'students' => StudentEnrollment::query()->where('status', EnrollmentStatus::Active->value)->count(),
                'employees' => Employee::query()->where('is_active', true)->count(),
                'recent_schools' => $recent,
            ];
        });
    }

    /**
     * The personal teaching block — "my day": today's periods off the
     * published timetable, homeroom register status, marklist pipeline and
     * LMS grading pile. Null when the user teaches nothing in this branch.
     *
     * @return array<string, mixed>|null
     */
    public function teacher(User $user, int $branchId): ?array
    {
        return Cache::remember(
            "dashboard:v1:teacher:{$user->id}:b{$branchId}:".Ethiopia::today(),
            self::PERSONAL_TTL,
            function () use ($user, $branchId): ?array {
                $employeeIds = Employee::query()
                    ->where('user_id', $user->id)
                    ->where('branch_id', $branchId)
                    ->pluck('id');

                if ($employeeIds->isEmpty()) {
                    return null;
                }

                $assignmentIds = SubjectAssignment::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('is_active', true)
                    ->pluck('id');

                $homerooms = SectionHomeroom::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->whereHas('academicYear', fn (Builder $q) => $q->where('status', 'active'))
                    ->with('section:id,name,grade_level_id', 'section.gradeLevel:id,code,name')
                    ->get();

                if ($assignmentIds->isEmpty() && $homerooms->isEmpty()) {
                    return null;
                }

                return [
                    'today' => $this->teacherToday($branchId, $assignmentIds->all()),
                    'homerooms' => $this->teacherHomerooms($homerooms),
                    'marklists' => $this->teacherMarklists($assignmentIds->all()),
                    'lms' => $this->teacherLms($assignmentIds->all()),
                ];
            },
        );
    }

    /**
     * Today's lessons for a set of subject assignments, timed from the bell
     * schedule of each slot's term. Sunday (and any day off the published
     * grid) simply yields an empty list.
     *
     * @param  list<int>  $assignmentIds
     * @return list<array<string, mixed>>
     */
    private function teacherToday(int $branchId, array $assignmentIds): array
    {
        if ($assignmentIds === []) {
            return [];
        }

        $dayOfWeek = Ethiopia::now()->isoWeekday(); // 1=Mon … 7=Sun
        if ($dayOfWeek === 7) {
            return [];
        }

        $slots = TimetableSlot::query()
            ->whereIn('subject_assignment_id', $assignmentIds)
            ->where('day_of_week', $dayOfWeek)
            ->whereHas('version', fn (Builder $q) => $q
                ->where('branch_id', $branchId)
                ->where('status', 'published')
                ->whereHas('term', fn (Builder $t) => $t->where('is_current', true)))
            ->with([
                'version:id,term_id',
                'subjectAssignment:id,subject_id,section_id',
                'subjectAssignment.subject:id,name,code',
                'subjectAssignment.section:id,name,grade_level_id',
                'subjectAssignment.section.gradeLevel:id,code',
                'room:id,name',
            ])
            ->get();

        if ($slots->isEmpty()) {
            return [];
        }

        // Bell schedule lookup: (term, period_number) → times.
        $periods = TermPeriod::query()
            ->whereIn('term_id', $slots->pluck('version.term_id')->unique())
            ->whereNotNull('period_number')
            ->get()
            ->keyBy(fn ($p) => $p->term_id.':'.$p->period_number);

        return $slots
            ->map(function (TimetableSlot $slot) use ($periods): array {
                $period = $periods->get($slot->version->term_id.':'.$slot->period_number);
                $section = $slot->subjectAssignment?->section;

                return [
                    'period' => $slot->period_number,
                    'starts_at' => $period ? substr((string) $period->starts_at, 0, 5) : null,
                    'ends_at' => $period ? substr((string) $period->ends_at, 0, 5) : null,
                    'subject' => $slot->subjectAssignment?->subject?->name,
                    'subject_code' => $slot->subjectAssignment?->subject?->code,
                    'section' => $section ? trim(($section->gradeLevel?->code ?? '').' '.$section->name) : null,
                    'section_id' => $section?->id,
                    'room' => $slot->room?->name,
                ];
            })
            ->sortBy('period')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SectionHomeroom>  $homerooms
     * @return list<array<string, mixed>>
     */
    private function teacherHomerooms($homerooms): array
    {
        if ($homerooms->isEmpty()) {
            return [];
        }

        $sectionIds = $homerooms->pluck('section_id')->unique();
        $today = Ethiopia::today();

        $enrolled = StudentEnrollment::query()
            ->whereIn('section_id', $sectionIds)
            ->where('status', EnrollmentStatus::Active->value)
            ->toBase()
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        $marked = AttendanceRecord::query()
            ->whereIn('section_id', $sectionIds)
            ->where('date', $today)
            ->toBase()
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        return $homerooms
            ->map(fn (SectionHomeroom $homeroom): array => [
                'section_id' => $homeroom->section_id,
                'section' => trim(($homeroom->section?->gradeLevel?->code ?? '').' '.($homeroom->section?->name ?? '')),
                'students' => (int) ($enrolled[$homeroom->section_id] ?? 0),
                'marked_today' => (int) ($marked[$homeroom->section_id] ?? 0),
            ])
            ->unique('section_id')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $assignmentIds
     * @return array{draft: int, submitted: int, approved: int}
     */
    private function teacherMarklists(array $assignmentIds): array
    {
        if ($assignmentIds === []) {
            return ['draft' => 0, 'submitted' => 0, 'approved' => 0];
        }

        $byStatus = Marklist::query()
            ->whereIn('subject_assignment_id', $assignmentIds)
            ->whereHas('term', fn (Builder $q) => $q->where('is_current', true))
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'draft' => (int) $byStatus->get(MarklistStatus::Draft->value, 0),
            'submitted' => (int) $byStatus->get(MarklistStatus::Submitted->value, 0),
            'approved' => (int) $byStatus->get(MarklistStatus::Approved->value, 0),
        ];
    }

    /**
     * @param  list<int>  $assignmentIds
     * @return array{to_grade: int, open_assignments: int}
     */
    private function teacherLms(array $assignmentIds): array
    {
        if ($assignmentIds === []) {
            return ['to_grade' => 0, 'open_assignments' => 0];
        }

        $toGrade = AssignmentSubmission::query()
            ->where('status', SubmissionStatus::Submitted->value)
            ->whereHas('assignment', fn (Builder $q) => $q
                ->whereIn('subject_assignment_id', $assignmentIds)
                ->where('status', AssignmentStatus::Published->value))
            ->count();

        $open = Assignment::query()
            ->whereIn('subject_assignment_id', $assignmentIds)
            ->where('status', AssignmentStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->count();

        return ['to_grade' => $toGrade, 'open_assignments' => $open];
    }

    /**
     * Anchor a query on the resolved scope. Branch beats school; a null pair
     * means platform-wide (Temari.et staff only — the controller guarantees
     * this can never be reached by school staff).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scoped(Builder $query, ?int $schoolId, ?int $branchId): Builder
    {
        return $this->scopedColumns($query, 'school_id', 'branch_id', $schoolId, $branchId);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopedColumns(Builder $query, string $schoolColumn, string $branchColumn, ?int $schoolId, ?int $branchId): Builder
    {
        if ($branchId !== null) {
            return $query->where($branchColumn, $branchId);
        }

        if ($schoolId !== null) {
            return $query->where($schoolColumn, $schoolId);
        }

        return $query;
    }

    /**
     * Cache a scope-level block per scope + day, so numbers roll over cleanly
     * at midnight Addis time.
     *
     * @template TValue of array
     *
     * @param  callable(): TValue  $compute
     * @return TValue
     */
    private function rememberScoped(string $block, ?int $schoolId, ?int $branchId, callable $compute): array
    {
        $scope = $branchId !== null ? "b{$branchId}" : ($schoolId !== null ? "s{$schoolId}" : 'platform');

        return Cache::remember("dashboard:v1:{$block}:{$scope}:".Ethiopia::today(), self::TTL, $compute);
    }
}
