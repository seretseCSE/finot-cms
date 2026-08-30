<?php

namespace App\Filament\Pages;


use App\Filament\Support\EmbeddableInHub;
use App\Filament\Support\HidesFromNavigation;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberGroup;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContributionMatrix extends Page
{
    use EmbeddableInHub;
    use HidesFromNavigation;

    protected string $view = 'filament.pages.contribution-matrix';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-table-cells';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Contributions';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'Contribution Form';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'contribution-matrix';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return \App\Support\RoleGate::can('page.report.contribution-matrix');
    }

    public ?int $academicYear = null;

    public ?int $department = null;

    public ?int $group = null;

    public ?string $type = null;

    public ?string $status = null;

    public Collection $members;

    public array $grid = [];

    public array $originalGrid = [];

    public bool $isDirty = false;

    public int $page = 1;

    public int $perPage = 25;

    public int $membersTotal = 0;

    /**
     * Pre-loaded contribution amounts: [group_id][month_name] => amount
     */
    protected array $amountCache = [];

    /**
     * Pre-loaded group names: [group_id] => name
     */
    protected array $groupNameCache = [];

    public array $months = [
        1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
        5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
        9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
    ];

    public function mount(): void
    {
        $this->academicYear = AcademicYear::where('status', 'Active')->first()?->id
            ?? AcademicYear::latest()->first()?->id;

        $this->members = collect();

        $this->loadMembersWithAssignments();
        $this->warmAmountCache();
        $this->loadGrid();
    }

    /**
     * Load members with their group assignments in 2 queries (no N+1).
     */
    protected function loadMembersWithAssignments(?callable $queryModifier = null): void
    {
        $query = DB::table('members')->whereNull('deleted_at');

        if ($queryModifier) {
            $queryModifier($query);
        }

        $this->membersTotal = (clone $query)->count();
        $lastPage = max(1, (int) ceil($this->membersTotal / $this->perPage));
        if ($this->page > $lastPage) {
            $this->page = $lastPage;
        }

        $membersData = $query
            ->orderBy('first_name')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get();

        if ($membersData->isEmpty()) {
            $this->members = collect();

            return;
        }

        $this->members = Member::hydrate($membersData->toArray());
        $this->attachCurrentGroupAssignments($this->members);
    }

    public function hydrate(): void
    {
        if (! $this->members instanceof Collection) {
            $this->members = collect($this->members);
        }

        if ($this->members->isEmpty()) {
            return;
        }

        $this->attachCurrentGroupAssignments($this->members);
        $this->warmAmountCache();
    }

    /**
     * Re-attach current group assignments after Livewire hydration.
     */
    protected function attachCurrentGroupAssignments(Collection $members): void
    {
        $memberIds = $members->pluck('id');

        $assignments = DB::table('member_group_assignments')
            ->whereIn('member_id', $memberIds)
            ->whereNull('effective_to')
            ->get()
            ->keyBy('member_id');

        $groupIds = $assignments->pluck('group_id')->unique()->filter();
        $this->groupNameCache = $groupIds->isNotEmpty()
            ? MemberGroup::whereIn('id', $groupIds)->pluck('name', 'id')->all()
            : [];

        $members->each(function ($member) use ($assignments) {
            $assignment = $assignments->get($member->id);
            if (! $assignment) {
                return;
            }

            $assignmentObject = new \stdClass();
            $assignmentObject->group_id = $assignment->group_id;
            $assignmentObject->group = (object) [
                'id' => $assignment->group_id,
                'name' => $this->groupNameCache[$assignment->group_id] ?? 'Unknown Group',
            ];
            $member->setRelation('currentGroupAssignment', $assignmentObject);
        });
    }

    public function loadMembers(): void
    {
        $this->loadMembersWithAssignments();
        $this->warmAmountCache();
    }

    public function loadMembersWithFilters(): void
    {
        $this->loadMembersWithAssignments(function ($query) {
            if ($this->department) {
                $query->where('department_id', $this->department);
            }

            if ($this->group) {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('member_group_assignments')
                        ->whereColumn('member_group_assignments.member_id', 'members.id')
                        ->where('group_id', $this->group)
                        ->whereNull('effective_to');
                });
            }

            if ($this->type) {
                $query->where('member_type', $this->type);
            }

            if ($this->status) {
                $query->where('status', $this->status);
            }
        });

        $this->warmAmountCache();
    }

    /**
     * Pre-load all contribution amounts for the current academic year and
     * the groups that appear in the current member set (1 query total).
     */
    protected function warmAmountCache(): void
    {
        $this->amountCache = [];

        if (! $this->academicYear) {
            return;
        }

        $groupIds = $this->members
            ->map(fn ($m) => $m->currentGroupAssignment?->group_id)
            ->filter()
            ->unique();

        if ($groupIds->isEmpty()) {
            return;
        }

        DB::table('contribution_amounts')
            ->where('academic_year_id', $this->academicYear)
            ->whereIn('group_id', $groupIds)
            ->get(['group_id', 'month_name', 'amount'])
            ->each(function ($row) {
                $this->amountCache[$row->group_id][$row->month_name] = (float) $row->amount;
            });
    }

    /**
     * Load payment status from DB into the matrix grid (1 query).
     */
    public function loadGrid(bool $reset = true): void
    {
        if ($reset) {
            $this->grid = [];
            $this->originalGrid = [];
            $this->isDirty = false;
        }

        if (! $this->academicYear || $this->members->isEmpty()) {
            return;
        }

        $newIds = $this->members->pluck('id')->filter(fn ($id) => ! isset($this->grid[$id]));

        foreach ($this->members as $member) {
            if (! isset($this->grid[$member->id])) {
                foreach (range(1, 12) as $monthNum) {
                    $this->grid[$member->id][$monthNum] = false;
                    $this->originalGrid[$member->id][$monthNum] = false;
                }
            }
        }

        if ($newIds->isEmpty()) {
            return;
        }

        $monthMap = array_flip($this->months);

        Contribution::where('academic_year_id', $this->academicYear)
            ->whereIn('member_id', $newIds)
            ->where('is_archived', false)
            ->get(['member_id', 'month_name', 'amount'])
            ->each(function ($c) use ($monthMap) {
                $monthNum = $monthMap[$c->month_name] ?? null;
                if ($monthNum && $c->amount > 0) {
                    $this->grid[$c->member_id][$monthNum] = true;
                    $this->originalGrid[$c->member_id][$monthNum] = true;
                }
            });
    }

    public function selectAllMonths(int $memberId): void
    {
        $member = $this->members->firstWhere('id', $memberId);
        if (! $member) {
            return;
        }

        foreach (range(1, 12) as $month) {
            if ($this->getMemberGroupAmount($member, $month) > 0) {
                $this->grid[$memberId][$month] = true;
            }
        }

        $this->isDirty = true;
    }

    public function clearAllMonths(int $memberId): void
    {
        foreach (range(1, 12) as $month) {
            $this->grid[$memberId][$month] = false;
        }

        $this->isDirty = true;
    }

    public function updated($property): void
    {
        if (! in_array($property, ['academicYear', 'department', 'group', 'type', 'status'], true)) {
            return;
        }

        if ($property === 'type') {
            $validIds = array_map('intval', array_keys($this->getGroupsForFilter()));
            if ($this->group && ! in_array((int) $this->group, $validIds, true)) {
                $this->group = null;
            }
        }

        $this->page = 1;

        if ($property === 'academicYear') {
            $this->warmAmountCache();
            $this->loadGrid();
        } else {
            $this->loadMembersWithFilters();
            $this->loadGrid();
        }
    }

    public function previousPage(): void
    {
        $this->gotoPage($this->page - 1);
    }

    public function nextPage(): void
    {
        $this->gotoPage($this->page + 1);
    }

    public function gotoPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->membersTotal / $this->perPage));
        $this->page = max(1, min($page, $lastPage));
        $this->loadMembersWithFilters();
        $this->loadGrid(false);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->membersTotal / max(1, $this->perPage)));
    }

    /**
     * Look up the amount from the pre-warmed cache (0 queries).
     */
    public function getMemberGroupAmount(Member $member, ?int $month = null): float
    {
        $groupId = $member->currentGroupAssignment?->group_id;
        if (! $groupId || ! $this->academicYear || ! $month) {
            return 0.0;
        }

        $monthName = $this->months[$month] ?? null;
        if (! $monthName) {
            return 0.0;
        }

        return $this->amountCache[$groupId][$monthName] ?? 0.0;
    }

    /**
     * Bulk save — single existence-check query + bulk insert + bulk archive.
     */
    public function save(): void
    {
        if (! $this->academicYear) {
            Notification::make()
                ->title('Academic Year Required')
                ->body('Please select an academic year to save contributions.')
                ->warning()
                ->send();

            return;
        }

        DB::beginTransaction();

        try {
            $members = $this->membersForSave();
            $memberIds = $members->pluck('id');

            // 1 query: load all existing non-archived contributions for the year
            $existing = Contribution::where('academic_year_id', $this->academicYear)
                ->whereIn('member_id', $memberIds)
                ->where('is_archived', false)
                ->get(['id', 'member_id', 'month_name'])
                ->groupBy('member_id')
                ->map(fn ($group) => $group->pluck('id', 'month_name'));

            $toInsert = [];
            $toArchiveIds = [];
            $skippedCount = 0;

            foreach ($members as $member) {
                $memberExisting = $existing->get($member->id, collect());

                foreach (range(1, 12) as $monthNum) {
                    $monthName = $this->months[$monthNum];
                    $isPaid = filter_var($this->grid[$member->id][$monthNum] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $groupAmount = $this->getMemberGroupAmount($member, $monthNum);
                    $hasExisting = $memberExisting->has($monthName);

                    if ($isPaid && $groupAmount === 0.0) {
                        $this->grid[$member->id][$monthNum] = false;
                        $skippedCount++;

                        continue;
                    }

                    if ($isPaid && $groupAmount > 0 && ! $hasExisting) {
                        $toInsert[] = [
                            'member_id' => $member->id,
                            'academic_year_id' => $this->academicYear,
                            'month_name' => $monthName,
                            'amount' => $groupAmount,
                            'payment_date' => now(),
                            'payment_method' => 'Cash',
                            'recorded_by' => Auth::id(),
                            'is_paid' => true,
                            'is_archived' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } elseif (! $isPaid && $hasExisting) {
                        $toArchiveIds[] = $memberExisting->get($monthName);
                    }
                }
            }

            $insertCount = count($toInsert);
            $archiveCount = count($toArchiveIds);

            if ($insertCount > 0) {
                foreach (array_chunk($toInsert, 500) as $chunk) {
                    Contribution::insert($chunk);
                }
            }

            if ($archiveCount > 0) {
                foreach (array_chunk($toArchiveIds, 500) as $chunk) {
                    Contribution::whereIn('id', $chunk)->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $this->isDirty = false;
            $this->loadGrid();

            $totalChanges = $insertCount + $archiveCount;

            if ($totalChanges === 0 && $skippedCount === 0) {
                Notification::make()
                    ->title('No changes to save')
                    ->body('Tick paid months in the grid, then click Save contributions.')
                    ->info()
                    ->send();
            } elseif ($skippedCount > 0) {
                Notification::make()
                    ->title('Contributions Saved with Warnings')
                    ->body("{$totalChanges} contributions recorded. {$skippedCount} items skipped — no contribution amount is set for those members' groups. Configure amounts in Contribution Settings.")
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Contributions Updated')
                    ->body("{$totalChanges} contributions have been recorded ({$insertCount} new, {$archiveCount} archived).")
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Save Failed')
                ->body('An error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Members on other pages stay in $grid until Save. Hydrate those rows so amounts resolve.
     */
    protected function membersForSave(): Collection
    {
        $current = $this->members->keyBy('id');
        $missingIds = collect(array_keys($this->grid))->diff($current->keys());

        if ($missingIds->isEmpty()) {
            return $this->members;
        }

        $extra = Member::hydrate(
            DB::table('members')->whereIn('id', $missingIds)->whereNull('deleted_at')->get()->toArray()
        );
        $this->attachCurrentGroupAssignments($extra);

        $merged = $this->members->merge($extra);
        $this->warmAmountCacheFor($merged);

        return $merged;
    }

    protected function warmAmountCacheFor(Collection $members): void
    {
        $previous = $this->members;
        $this->members = $members;
        $this->warmAmountCache();
        $this->members = $previous;
    }

    protected function getHeaderActions(): array
    {
        if ($this->embeddedInHub) {
            return [];
        }

        return [
            Action::make('save')
                ->label('Save contributions')
                ->color('success')
                ->icon('heroicon-o-check')
                ->disabled(fn () => ! $this->academicYear)
                ->action('save'),

            Action::make('export')
                ->label('Export to Excel')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => ! \App\Support\RoleGate::is('nibret_hisab_head'))
                ->action('export'),
        ];
    }

    public function getFilterOptions(): array
    {
        return [
            'academic_years' => AcademicYear::latest()->pluck('name', 'id')->toArray(),
            'departments' => Department::pluck('name_en', 'id')->toArray(),
            'groups' => $this->getGroupsForFilter(),
            'types' => ['Kids' => 'Kids', 'Youth' => 'Youth', 'Adult' => 'Adult'],
        ];
    }

    /**
     * Groups whose type belongs to the selected member type (Kids / Youth / Adult).
     *
     * @return array<int, string>
     */
    public function getGroupsForFilter(): array
    {
        $query = MemberGroup::query()->active()->orderBy('name');

        if (filled($this->type)) {
            $query->whereIn('group_type', $this->groupTypesForMemberType($this->type));
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * @return list<string>
     */
    protected function groupTypesForMemberType(string $memberType): array
    {
        return match ($memberType) {
            'Kids' => ['Kids', 'Elder Kids'],
            'Youth' => ['Youth', 'Youngsters'],
            'Adult' => ['Adult', 'Finot Family'],
            default => [$memberType],
        };
    }

    public function getSummaryStats(): array
    {
        if ($this->members->isEmpty() || ! $this->academicYear) {
            return [
                'total_members' => 0,
                'total_expected' => 0,
                'total_collected' => 0,
                'completion_rate' => 0,
                'months_completed' => 0,
            ];
        }

        $totalExpected = 0;
        $totalCollected = 0;
        $monthsCompleted = 0;
        $totalPossiblePayments = 0;

        foreach ($this->members as $member) {
            foreach (range(1, 12) as $month) {
                $amount = $this->getMemberGroupAmount($member, $month);
                if ($amount > 0) {
                    $totalExpected += $amount;
                    $totalPossiblePayments++;

                    if ($this->grid[$member->id][$month] ?? false) {
                        $totalCollected += $amount;
                        $monthsCompleted++;
                    }
                }
            }
        }

        $completionRate = $totalPossiblePayments > 0
            ? round(($monthsCompleted / $totalPossiblePayments) * 100, 1)
            : 0;

        return [
            'total_members' => $this->members->count(),
            'total_expected' => $totalExpected,
            'total_collected' => $totalCollected,
            'completion_rate' => $completionRate,
            'months_completed' => $monthsCompleted,
        ];
    }

    public function export(): void
    {
        if (! $this->academicYear) {
            Notification::make()
                ->title('Academic Year Required')
                ->body('Please select an academic year to export contributions.')
                ->warning()
                ->send();

            return;
        }

        try {
            $academicYear = AcademicYear::find($this->academicYear);
            $filename = 'contribution_matrix_' . str_replace(' ', '_', strtolower($academicYear->name)) . '_' . date('Y-m-d') . '.xlsx';

            response()->streamDownload(function () {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                $headers = ['Member Name', 'Member ID', 'Group'];
                foreach ($this->months as $monthName) {
                    $headers[] = $monthName;
                }
                $headers[] = 'Total';
                $sheet->fromArray($headers, null, 'A1');

                $row = 2;
                foreach ($this->members as $member) {
                    $groupName = $this->groupNameCache[$member->currentGroupAssignment?->group_id ?? 0] ?? 'N/A';

                    $rowData = [
                        $member->first_name . ' ' . $member->father_name,
                        $member->id,
                        $groupName,
                    ];

                    $total = 0;
                    foreach (range(1, 12) as $month) {
                        $amount = ($this->grid[$member->id][$month] ?? false)
                            ? $this->getMemberGroupAmount($member, $month)
                            : 0;
                        $rowData[] = $amount > 0 ? 'Birr ' . number_format($amount, 2) : '-';
                        $total += $amount;
                    }
                    $rowData[] = 'Birr ' . number_format($total, 2);

                    $sheet->fromArray($rowData, null, 'A' . $row);
                    $row++;
                }

                foreach (range('A', 'Z') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save('php://output');
            }, $filename);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('An error occurred while exporting: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshData(): void
    {
        $this->loadMembersWithFilters();
        $this->loadGrid();

        Notification::make()
            ->title('Grid reloaded')
            ->body('Unchecked any unsaved ticks and loaded the latest payments from the database.')
            ->success()
            ->send();
    }

    public function markAllPaid(int $month): void
    {
        if (! $this->academicYear) {
            Notification::make()
                ->title('Academic Year Required')
                ->body('Please select an academic year to mark payments.')
                ->warning()
                ->send();

            return;
        }

        $count = 0;
        foreach ($this->members as $member) {
            $amount = $this->getMemberGroupAmount($member, $month);
            if ($amount > 0 && ! ($this->grid[$member->id][$month] ?? false)) {
                $this->grid[$member->id][$month] = true;
                $count++;
            }
        }

        $this->isDirty = true;

        Notification::make()
            ->title('Column updated')
            ->body("Marked {$count} members as paid for {$this->months[$month]}. Click Save to record them.")
            ->success()
            ->send();
    }

    public function markAllUnpaid(int $month): void
    {
        if (! $this->academicYear) {
            Notification::make()
                ->title('Academic Year Required')
                ->body('Please select an academic year to unmark payments.')
                ->warning()
                ->send();

            return;
        }

        $count = 0;
        foreach ($this->members as $member) {
            if ($this->grid[$member->id][$month] ?? false) {
                $this->grid[$member->id][$month] = false;
                $count++;
            }
        }

        $this->isDirty = true;

        Notification::make()
            ->title('Column updated')
            ->body("Cleared {$count} members for {$this->months[$month]}. Click Save to record them.")
            ->success()
            ->send();
    }
}
