<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Helpers\EthiopianDateHelper;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViewMemberTimeline extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.member-resource.pages.view-member-timeline';

    public ?array $filters = [];

    public string $activeTab = 'all';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $events = [];

    public int $page = 1;

    public int $perPage = 10;

    public bool $hasSearched = false;

    public bool $hasMore = false;

    public function mount(): void
    {
        $this->form->fill([
            'name' => null,
            'member_id' => null,
            'phone' => null,
            'group_name' => null,
            'parent_name' => null,
        ]);

        $memberId = request()->query('member_id');

        if (filled($memberId)) {
            $this->form->fill([
                'member_id' => $memberId,
            ]);

            $this->searchTimeline();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->placeholder('First name or Father name')
                ->maxLength(200),

            Forms\Components\TextInput::make('member_id')
                ->label('Member ID')
                ->numeric(),

            Forms\Components\TextInput::make('phone')
                ->label('Phone')
                ->tel(),

            Forms\Components\TextInput::make('group_name')
                ->label('Group Name')
                ->maxLength(200),

            Forms\Components\TextInput::make('parent_name')
                ->label('Parent / Guardian Name')
                ->maxLength(200),
        ];
    }

    protected function getFormColumns(): int
    {
        return 3;
    }

    public function searchTimeline(): void
    {
        $this->filters = $this->form->getState();
        $this->hasSearched = true;

        if (! $this->hasAnyFilterApplied()) {
            $this->events = [];
            $this->hasMore = false;

            Notification::make()
                ->title('Please apply at least one filter to view timeline')
                ->warning()
                ->send();

            return;
        }

        $this->page = 1;
        $this->events = [];
        $this->loadEvents(append: false);
    }

    public function loadMore(): void
    {
        $this->page++;
        $this->loadEvents(append: true);
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;

        if (! $this->hasSearched || ! $this->hasAnyFilterApplied()) {
            $this->events = [];
            $this->hasMore = false;
            return;
        }

        $this->page = 1;
        $this->events = [];
        $this->loadEvents(append: false);
    }

    public function getTabs(): array
    {
        $allowed = $this->getAllowedEventGroups();

        $tabs = [
            'all' => 'All',
            'groups' => 'Groups',
            'education' => 'Education',
            'attendance' => 'Attendance',
            'contributions' => 'Contributions',
        ];

        if ($allowed === ['all']) {
            return $tabs;
        }

        $filtered = [];

        foreach ($tabs as $key => $label) {
            if ($key === 'all') {
                $filtered[$key] = $label;
                continue;
            }

            if (in_array($key, $allowed, true)) {
                $filtered[$key] = $label;
            }
        }

        return $filtered;
    }

    public function hasAnyFilterApplied(): bool
    {
        $state = $this->filters ?: $this->form->getState();

        return filled($state['name'] ?? null)
            || filled($state['member_id'] ?? null)
            || filled($state['phone'] ?? null)
            || filled($state['group_name'] ?? null)
            || filled($state['parent_name'] ?? null);
    }

    protected function loadEvents(bool $append): void
    {
        $filters = $this->filters;
        $tab = $this->activeTab;
        $allowed = $this->getAllowedEventGroups();

        if ($allowed !== ['all'] && $tab !== 'all' && ! in_array($tab, $allowed, true)) {
            $this->events = [];
            $this->hasMore = false;
            return;
        }

        $cacheKey = $this->getCacheKey($filters, $tab, $this->page);

        $result = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($filters, $tab, $allowed): array {
            $query = $this->buildUnifiedTimelineQuery($filters, $tab, $allowed);
            $offset = ($this->page - 1) * $this->perPage;

            $rows = $query
                ->orderByDesc('event_date')
                ->offset($offset)
                ->limit($this->perPage + 1)
                ->get();

            $hasMore = $rows->count() > $this->perPage;

            $rows = $rows->take($this->perPage)->values();

            return [
                'events' => $rows->toArray(),
                'has_more' => $hasMore,
            ];
        });

        $this->hasMore = (bool) ($result['has_more'] ?? false);

        if ($append) {
            $this->events = array_values(array_merge($this->events, $result['events'] ?? []));
            return;
        }

        $this->events = $result['events'] ?? [];
    }

    protected function buildUnifiedTimelineQuery(array $filters, string $tab, array $allowed): QueryBuilder
    {
        $base = DB::query();

        $queries = [];

        $include = function (string $group) use ($tab, $allowed): bool {
            if ($allowed === ['all']) {
                return $tab === 'all' || $tab === $group;
            }

            if ($tab === 'all') {
                return in_array($group, $allowed, true);
            }

            return $tab === $group && in_array($group, $allowed, true);
        };

        if ($include('groups') && Schema::hasTable('member_group_assignments')) {
            $groupQuery = DB::table('member_group_assignments as mga')
                ->join('members as m', 'm.id', '=', 'mga.member_id')
                ->join('member_groups as g', 'g.id', '=', 'mga.group_id')
                ->leftJoin('users as u', 'u.id', '=', 'mga.assigned_by')
                ->select([
                    DB::raw("'groups' as event_group"),
                    DB::raw("'group_join' as event_type"),
                    'mga.effective_from as event_date',
                    DB::raw("CONCAT('Assigned to ', g.name) as description"),
                    'u.name as performed_by',
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $groupRemovalQuery = DB::table('member_group_assignments as mga')
                ->join('members as m', 'm.id', '=', 'mga.member_id')
                ->join('member_groups as g', 'g.id', '=', 'mga.group_id')
                ->leftJoin('users as u', 'u.id', '=', 'mga.removed_by')
                ->whereNotNull('mga.effective_to')
                ->select([
                    DB::raw("'groups' as event_group"),
                    DB::raw("'group_removed' as event_type"),
                    'mga.effective_to as event_date',
                    DB::raw("CONCAT('Removed from ', g.name) as description"),
                    'u.name as performed_by',
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $queries[] = $this->applyMemberFiltersToQuery($groupQuery, $filters);
            $queries[] = $this->applyMemberFiltersToQuery($groupRemovalQuery, $filters);
        }

        if ($include('education')) {
            if (Schema::hasTable('student_enrollments')) {
                $educationQuery = DB::table('student_enrollments as se')
                    ->join('members as m', 'm.id', '=', 'se.member_id')
                    ->select([
                        DB::raw("'education' as event_group"),
                        DB::raw("CASE WHEN se.status = 'Withdrawn' THEN 'education_withdrawn' WHEN se.status = 'Promoted' THEN 'education_promoted' ELSE 'education_enrolled' END as event_type"),
                        'se.enrolled_date as event_date',
                        DB::raw("CONCAT('Class ID: ', se.class_id, ' (Year ID: ', se.academic_year_id, ')') as description"),
                        DB::raw('NULL as performed_by'),
                        'm.id as member_id',
                        'm.member_code as member_code',
                        DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                    ]);

                $queries[] = $this->applyMemberFiltersToQuery($educationQuery, $filters);
            }

            if (Schema::hasTable('enrollments')) {
                $educationQuery = DB::table('enrollments as e')
                    ->join('members as m', 'm.id', '=', 'e.student_id')
                    ->leftJoin('school_classes as sc', 'sc.id', '=', 'e.school_class_id')
                    ->select([
                        DB::raw("'education' as event_group"),
                        DB::raw("CASE WHEN e.is_active = 0 THEN 'education_withdrawn' ELSE 'education_enrolled' END as event_type"),
                        'e.enrollment_date as event_date',
                        DB::raw("CONCAT('Class: ', COALESCE(sc.name, 'N/A')) as description"),
                        DB::raw('NULL as performed_by'),
                        'm.id as member_id',
                        'm.member_code as member_code',
                        DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                    ]);

                $queries[] = $this->applyMemberFiltersToQuery($educationQuery, $filters);
            }
        }

        if ($include('attendance') && Schema::hasTable('student_attendance') && Schema::hasTable('attendance_sessions')) {
            $attendanceQuery = DB::table('student_attendance as sa')
                ->join('members as m', 'm.id', '=', 'sa.student_id')
                ->join('attendance_sessions as s', 's.id', '=', 'sa.session_id')
                ->leftJoin('users as u', 'u.id', '=', 'sa.marked_by')
                ->select([
                    DB::raw("'attendance' as event_group"),
                    DB::raw("'attendance_marked' as event_type"),
                    's.session_date as event_date',
                    DB::raw("CONCAT('Attendance: ', sa.status) as description"),
                    'u.name as performed_by',
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $queries[] = $this->applyMemberFiltersToQuery($attendanceQuery, $filters);
        }

        if ($include('contributions') && Schema::hasTable('contributions')) {
            $contributionQuery = DB::table('contributions as c')
                ->join('members as m', 'm.id', '=', 'c.member_id')
                ->leftJoin('users as u', 'u.id', '=', 'c.recorded_by')
                ->select([
                    DB::raw("'contributions' as event_group"),
                    DB::raw("'contribution_payment' as event_type"),
                    'c.payment_date as event_date',
                    DB::raw("CONCAT('Payment recorded: ', c.amount, ' (', c.month_name, ')') as description"),
                    'u.name as performed_by',
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $queries[] = $this->applyMemberFiltersToQuery($contributionQuery, $filters);
        }

        if ($tab === 'all' && Schema::hasTable('member_parent_guardians')) {
            $guardianQuery = DB::table('member_parent_guardians as mpg')
                ->join('members as m', 'm.id', '=', 'mpg.member_id')
                ->select([
                    DB::raw("'groups' as event_group"),
                    DB::raw("'parent_guardian_added' as event_type"),
                    'mpg.created_at as event_date',
                    DB::raw("CONCAT('Parent/Guardian: ', mpg.parent_name, ' (', mpg.relationship, ')') as description"),
                    DB::raw('NULL as performed_by'),
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $queries[] = $this->applyMemberFiltersToQuery($guardianQuery, $filters);
        }

        if ($tab === 'all' && Schema::hasTable('tour_passengers') && Schema::hasTable('tours')) {
            $tourQuery = DB::table('tour_passengers as tp')
                ->join('members as m', 'm.id', '=', 'tp.member_id')
                ->join('tours as t', 't.id', '=', 'tp.tour_id')
                ->leftJoin('users as u', 'u.id', '=', 'tp.registered_by')
                ->select([
                    DB::raw("'groups' as event_group"),
                    DB::raw("'tour_registered' as event_type"),
                    'tp.registration_date as event_date',
                    DB::raw("CONCAT('Tour: ', t.place, ' (', tp.status, ')') as description"),
                    'u.name as performed_by',
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $queries[] = $this->applyMemberFiltersToQuery($tourQuery, $filters);
        }

        if ($tab === 'all' && Schema::hasTable('audit_logs')) {
            $auditQuery = DB::table('audit_logs as al')
                ->join('members as m', 'm.id', '=', 'al.entity_id')
                ->leftJoin('users as u', 'u.id', '=', 'al.user_id')
                ->where('al.entity_type', 'members')
                ->select([
                    DB::raw("'groups' as event_group"),
                    DB::raw("'status_change' as event_type"),
                    'al.created_at as event_date',
                    DB::raw("CONCAT('Audit: ', al.action_type, COALESCE(CONCAT(' - ', al.notes), '')) as description"),
                    'u.name as performed_by',
                    'm.id as member_id',
                    'm.member_code as member_code',
                    DB::raw("CONCAT(m.first_name, ' ', m.father_name, ' ', m.grandfather_name) as member_name"),
                ]);

            $queries[] = $this->applyMemberFiltersToQuery($auditQuery, $filters);
        }

        if (empty($queries)) {
            return $base->fromSub(DB::query()->select([
                DB::raw("'all' as event_group"),
                DB::raw("'none' as event_type"),
                DB::raw('NULL as event_date'),
                DB::raw("'' as description"),
                DB::raw('NULL as performed_by'),
                DB::raw('NULL as member_id'),
                DB::raw("'' as member_code"),
                DB::raw("'' as member_name"),
            ])->whereRaw('1 = 0'), 'timeline');
        }

        /** @var QueryBuilder $union */
        $union = array_shift($queries);

        foreach ($queries as $q) {
            $union->unionAll($q);
        }

        return $base->fromSub($union, 'timeline');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function applyMemberFiltersToQuery($query, array $filters)
    {
        if (filled($filters['member_id'] ?? null)) {
            $query->where('m.id', (int) $filters['member_id']);
        }

        if (filled($filters['phone'] ?? null)) {
            $query->where('m.phone', $filters['phone']);
        }

        if (filled($filters['name'] ?? null)) {
            $name = trim((string) $filters['name']);

            $query->where(function ($q) use ($name): void {
                $q->where('m.first_name', 'like', "%{$name}%")
                    ->orWhere('m.father_name', 'like', "%{$name}%");
            });
        }

        if (filled($filters['group_name'] ?? null) && Schema::hasTable('member_group_assignments')) {
            $groupName = trim((string) $filters['group_name']);

            $query->whereExists(function ($sub) use ($groupName): void {
                $sub->select(DB::raw(1))
                    ->from('member_group_assignments as mga2')
                    ->join('member_groups as g2', 'g2.id', '=', 'mga2.group_id')
                    ->whereColumn('mga2.member_id', 'm.id')
                    ->where('g2.name', 'like', "%{$groupName}%");
            });
        }

        if (filled($filters['parent_name'] ?? null) && Schema::hasTable('member_parent_guardians')) {
            $parentName = trim((string) $filters['parent_name']);

            $query->whereExists(function ($sub) use ($parentName): void {
                $sub->select(DB::raw(1))
                    ->from('member_parent_guardians as mpg')
                    ->whereColumn('mpg.member_id', 'm.id')
                    ->where('mpg.parent_name', 'like', "%{$parentName}%");
            });
        }

        return $query;
    }

    protected function getCacheKey(array $filters, string $tab, int $page): string
    {
        $memberId = $filters['member_id'] ?? null;

        if (filled($memberId)) {
            return 'timeline:member:' . $memberId . ':tab:' . $tab . ':page:' . $page;
        }

        $payload = json_encode([
            'filters' => $filters,
            'tab' => $tab,
            'page' => $page,
            'roles' => Auth::user()?->roles?->pluck('name')->all(),
        ]);

        return 'timeline:search:' . hash('sha256', (string) $payload);
    }

    /**
     * @return array<int, string>
     */
    protected function getAllowedEventGroups(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        if ($user->hasRole(['admin', 'superadmin', 'hr_head'])) {
            return ['all'];
        }

        if ($user->hasRole(['education_head'])) {
            return ['education', 'attendance'];
        }

        if ($user->hasRole(['finance_head'])) {
            return ['contributions'];
        }

        return ['groups'];
    }

    public function formatEthiopianDate(?string $date): string
    {
        if (blank($date)) {
            return '';
        }

        return app(EthiopianDateHelper::class)->toString($date);
    }

    public function getTitle(): string
    {
        return 'Member Timeline';
    }
}

