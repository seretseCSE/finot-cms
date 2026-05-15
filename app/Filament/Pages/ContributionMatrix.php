<?php

namespace App\Filament\Pages;

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
    protected string $view = 'filament.pages.contribution-matrix';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-table-cells';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'Contribution';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'contribution-matrix';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('page.report.contribution-matrix');
    }

    // Filter properties
    public ?int $academicYear = null;

    public ?int $department = null;

    public ?int $group = null;

    public ?string $type = null;

    public ?string $status = null;

    // Data properties
    public Collection $members;

    // Table state
    public array $grid = []; // [member_id][month_index] => boolean
    public array $originalGrid = []; // [member_id][month_index] => boolean (original state)

    public bool $isDirty = false;

    public array $months = [
        1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
        5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
        9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
    ];

    public function mount(): void
    {
        $this->academicYear = AcademicYear::where('status', 'Active')->first()?->id ?? AcademicYear::latest()->first()?->id;

        // Try direct assignment
        $this->members = collect();

        // Test with a simple query including relationships
        $testMembers = DB::table('members')
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get();

        \Log::info('ContributionMatrix: Direct DB query result', ['count' => $testMembers->count()]);

        if ($testMembers->isNotEmpty()) {
            // Convert to Member models with relationships
            $this->members = Member::hydrate($testMembers->toArray());

            // Load current group assignments for all members
            $memberIds = $this->members->pluck('id');
            $assignments = DB::table('member_group_assignments')
                ->whereIn('member_id', $memberIds)
                ->whereNull('effective_to')
                ->get();

            // Attach assignments to members with proper group relationship
            $this->members->each(function ($member) use ($assignments) {
                $assignment = $assignments->firstWhere('member_id', $member->id);
                if ($assignment) {
                    // Create a proper assignment object with group relationship
                    $assignmentObject = new \stdClass();
                    $assignmentObject->group_id = $assignment->group_id;

                    // Create group object for the relationship
                    $groupObject = new \stdClass();
                    $groupObject->id = $assignment->group_id;

                    $memberGroup = \App\Models\MemberGroup::find($assignment->group_id);
                    $groupObject->name = $memberGroup ? $memberGroup->name : 'Unknown Group';

                    $assignmentObject->group = $groupObject;

                    $member->setRelation('currentGroupAssignment', $assignmentObject);
                }
            });

            \Log::info('ContributionMatrix: Hydrated members with assignments', ['count' => $this->members->count()]);
        }

        $this->loadGrid();
    }

    public function loadMembers(): void
    {
        // Debug: Check if we can access the Member model
        try {
            // Test database connection
            $dbTest = DB::select('SELECT 1 as test');
            \Log::info('ContributionMatrix: DB test', ['result' => $dbTest]);

            $totalMembers = Member::count();
            \Log::info('ContributionMatrix: Total members in database', ['count' => $totalMembers]);

            // Always load all members initially
            $query = Member::query();
            $sql = $query->toSql();
            \Log::info('ContributionMatrix: Query SQL', ['sql' => $sql]);

            // Try with raw SQL
            $rawMembers = DB::select('SELECT * FROM members WHERE deleted_at IS NULL ORDER BY first_name LIMIT 3');
            \Log::info('ContributionMatrix: Raw SQL result', ['count' => count($rawMembers)]);

            $this->members = $query->orderBy('first_name')->get();

            \Log::info('ContributionMatrix: Loaded all members', ['count' => $this->members->count()]);

            // Debug: Log first few member IDs
            if ($this->members->isNotEmpty()) {
                \Log::info('ContributionMatrix: First member IDs', $this->members->take(3)->pluck('id')->toArray());
            }
        } catch (\Exception $e) {
            \Log::error('ContributionMatrix: Error loading members', ['error' => $e->getMessage()]);
            $this->members = collect();
        }
    }

    public function loadMembersWithFilters(): void
    {
        $query = DB::table('members')->whereNull('deleted_at');

        if ($this->department) {
            $query->where('department_id', $this->department);
        }

        if ($this->group) {
            $query->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
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

        $membersData = $query->orderBy('first_name')->get();

        if ($membersData->isNotEmpty()) {
            // Convert to Member models
            $this->members = Member::hydrate($membersData->toArray());

            // Load current group assignments
            $memberIds = $this->members->pluck('id');
            $assignments = DB::table('member_group_assignments')
                ->whereIn('member_id', $memberIds)
                ->whereNull('effective_to')
                ->get();

            // Attach assignments to members
            $this->members->each(function ($member) use ($assignments) {
                $assignment = $assignments->firstWhere('member_id', $member->id);
                if ($assignment) {
                    $member->setRelation('currentGroupAssignment', (object) [
                        'group_id' => $assignment->group_id,
                    ]);
                }
            });
        } else {
            $this->members = collect();
        }

        \Log::info('ContributionMatrix: Loaded members with filters', [
            'count' => $this->members->count(),
            'department' => $this->department,
            'group' => $this->group,
            'type' => $this->type,
            'status' => $this->status,
        ]);
    }

    /**
     * Load payment status from DB into the matrix grid
     */
    public function loadGrid(): void
    {
        $this->grid = [];
        $this->isDirty = false;

        // Load existing contributions if academic year is selected
        if ($this->academicYear) {
            $memberIds = $this->members->pluck('id');
            \Log::info('ContributionMatrix: Loading grid', [
                'academicYear' => $this->academicYear,
                'memberCount' => $memberIds->count(),
                'memberIds' => $memberIds->take(5)->toArray()
            ]);

            $existing = Contribution::where('academic_year_id', $this->academicYear)
                ->whereIn('member_id', $memberIds)
                ->where('is_archived', false)
                ->get(['member_id', 'month_name', 'amount']);

            \Log::info('ContributionMatrix: Found contributions', ['count' => $existing->count()]);

            // Map month names to numbers (Ethiopian calendar)
            $monthMap = [
                'Meskerem' => 1, 'Tikimt' => 2, 'Hidar' => 3, 'Tahsas' => 4,
                'Tir' => 5, 'Yekatit' => 6, 'Megabit' => 7, 'Miazia' => 8,
                'Ginbot' => 9, 'Sene' => 10, 'Hamle' => 11, 'Nehasse' => 12,
            ];

            foreach ($existing as $contribution) {
                $memberId = $contribution->member_id;
                $monthNum = $monthMap[$contribution->month_name] ?? null;

                if ($memberId && $monthNum && $contribution->amount > 0) {
                    $this->grid[$memberId][$monthNum] = true;

                    // Only set originalGrid on first load
                    if (empty($this->originalGrid[$memberId][$monthNum] ?? null)) {
                        $this->originalGrid[$memberId][$monthNum] = true;
                    }
                }
            }

            \Log::info('ContributionMatrix: Grid populated', [
                'gridCount' => count($this->grid),
                'sampleGrid' => array_slice($this->grid, 0, 3, true)
            ]);
        }
    }

    /**
     * Handle state changes
     */
    public function toggle(int $memberId, int $month): void
    {
        $this->grid[$memberId][$month] = ! ($this->grid[$memberId][$month] ?? false);
        $this->isDirty = true;

        // Auto-save when checkbox is toggled
        $this->autoSaveToggle($memberId, $month);
    }

    public function selectAllMonths(int $memberId): void
    {
        foreach (range(1, 12) as $month) {
            $this->grid[$memberId][$month] = true;
        }
        $this->isDirty = true;

        Notification::make()
            ->title('All Months Selected')
            ->body('All 12 months have been selected for this member.')
            ->info()
            ->send();
    }

    protected function autoSaveToggle(int $memberId, int $month): void
    {
        if (! $this->academicYear) {
            return;
        }

        $member = $this->members->firstWhere('id', $memberId);
        if (! $member) {
            return;
        }

        $monthNames = [
            1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
            5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
            9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
        ];

        $monthName = $monthNames[$month] ?? null;
        $isPaid = $this->grid[$memberId][$month] ?? false;
        $groupAmount = $this->getMemberGroupAmount($member, $month);

        DB::beginTransaction();
        try {
            if ($isPaid && $groupAmount > 0) {
                // Check if contribution already exists
                $existing = Contribution::where('member_id', $memberId)
                    ->where('academic_year_id', $this->academicYear)
                    ->where('month_name', $monthName)
                    ->where('is_archived', false)
                    ->first();

                if (! $existing) {
                    // Create new contribution
                    Contribution::create([
                        'member_id' => $memberId,
                        'academic_year_id' => $this->academicYear,
                        'month_name' => $monthName,
                        'amount' => $groupAmount,
                        'payment_date' => now(),
                        'payment_method' => 'Cash',
                        'recorded_by' => Auth::id(),
                        'is_archived' => false,
                    ]);
                }
            } else {
                // Archive existing contribution if unchecked
                Contribution::where('member_id', $memberId)
                    ->where('academic_year_id', $this->academicYear)
                    ->where('month_name', $monthName)
                    ->where('is_archived', false)
                    ->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                    ]);
            }

            DB::commit();

            // Dispatch event to show autosave indicator
            $this->dispatch('autosave-completed', [
                'memberId' => $memberId,
                'month' => $month,
                'status' => $isPaid ? 'saved' : 'archived',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Autosave failed for contribution toggle', [
                'memberId' => $memberId,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Filter update hook
     */
    public function updated($property): void
    {
        if (in_array($property, ['academicYear', 'department', 'group', 'type', 'status'])) {
            if ($property === 'academicYear') {
                $this->loadGrid();
            } else {
                $this->loadMembersWithFilters();
                $this->loadGrid();
            }
        }
    }

    /**
     * Get the constant amount for a member's group
     */
    public function getMemberGroupAmount(Member $member, ?int $month = null): float
    {
        $groupId = $member->currentGroupAssignment?->group_id;
        if (! $groupId || ! $this->academicYear) {
            return 0.0;
        }

        // If no month specified, return 0 (we need month-specific amounts)
        if (! $month) {
            return 0.0;
        }

        // Map month numbers to Ethiopian month names
        $monthNames = [
            1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
            5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
            9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
        ];

        $monthName = $monthNames[$month] ?? null;
        if (! $monthName) {
            return 0.0;
        }

        // Get the month-specific amount from contribution_amounts table
        $amount = DB::table('contribution_amounts')
            ->where('group_id', $groupId)
            ->where('academic_year_id', $this->academicYear)
            ->where('month_name', $monthName)
            ->value('amount');

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    /**
     * Bulk save using optimized upsert
     */
    public function save(): void
    {
        if (! $this->isDirty) {
            return;
        }

        // Require academic year to save contributions
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
            $monthNames = [
                1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
                5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
                9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
            ];

            $toInsert = [];

            foreach ($this->members as $member) {
                foreach (range(1, 12) as $monthNum) {
                    $groupAmount = $this->getMemberGroupAmount($member, $monthNum);
                    $isPaid = $this->grid[$member->id][$monthNum] ?? false;
                    $monthName = $monthNames[$monthNum];

                    if ($isPaid && $groupAmount > 0) {
                        // Check if contribution already exists
                        $existing = Contribution::where('member_id', $member->id)
                            ->where('academic_year_id', $this->academicYear)
                            ->where('month_name', $monthName)
                            ->where('is_archived', false)
                            ->first();

                        if (! $existing) {
                            $toInsert[] = [
                                'member_id' => $member->id,
                                'academic_year_id' => $this->academicYear,
                                'month_name' => $monthName,
                                'amount' => $groupAmount,
                                'payment_date' => now(),
                                'payment_method' => 'Cash',
                                'recorded_by' => Auth::id(),
                                'is_archived' => false,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    } else {
                        // Archive existing contribution if unchecked
                        Contribution::where('member_id', $member->id)
                            ->where('academic_year_id', $this->academicYear)
                            ->where('month_name', $monthName)
                            ->where('is_archived', false)
                            ->update([
                                'is_archived' => true,
                                'archived_at' => now(),
                            ]);
                    }
                }
            }

            // Insert new contributions
            $insertCount = 0;
            $updateCount = 0;

            if (! empty($toInsert)) {
                Contribution::insert($toInsert);
                $insertCount = count($toInsert);
            }

            // Count contributions that are checked (new + archived)
            foreach ($this->members as $member) {
                foreach (range(1, 12) as $monthNum) {
                    $isPaid = $this->grid[$member->id][$monthNum] ?? false;
                    $monthName = $monthNames[$monthNum];

                    if ($isPaid) {
                        // Check if contribution already exists
                        $existing = Contribution::where('member_id', $member->id)
                            ->where('academic_year_id', $this->academicYear)
                            ->where('month_name', $monthName)
                            ->where('is_archived', false)
                            ->first();

                        if (!$existing) {
                            $insertCount++;
                        } else {
                            // Only count as update if we're actually changing the state
                            $wasOriginallyPaid = $this->originalGrid[$member->id][$monthNum] ?? false;
                            if ($wasOriginallyPaid !== $isPaid) {
                                $updateCount++;
                            }
                        }
                    }
                }
            }

            DB::commit();

            $this->isDirty = false;
            $this->loadGrid();

            $totalChanges = $insertCount + $updateCount;
            Notification::make()
                ->title('Contributions Updated')
                ->body("{$totalChanges} contributions have been recorded ({$insertCount} new, {$updateCount} updated).")
                ->success()
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Save Failed')
                ->body('An error occurred: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->color('success')
                ->icon('heroicon-o-check')
                ->disabled(fn () => ! $this->isDirty)
                ->action('save'),

            Action::make('export')
                ->label('Export to Excel')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('export'),

            Action::make('refresh')
                ->label('Refresh Data')
                ->color('secondary')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshData'),
        ];
    }

    public function getFilterOptions(): array
    {
        return [
            'academic_years' => AcademicYear::latest()->pluck('name', 'id')->toArray(),
            'departments' => Department::pluck('name_en', 'id')->toArray(),
            'groups' => MemberGroup::pluck('name', 'id')->toArray(),
            'types' => ['Adult' => 'Adult', 'Youth' => 'Youth', 'Kids' => 'Kids'],
            'statuses' => ['Active' => 'Active', 'Member' => 'Member', 'Draft' => 'Draft'],
        ];
    }

    /**
     * Get summary statistics for the current filters
     */
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

    /**
     * Export contributions to Excel
     */
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
            $filename = 'contribution_matrix_'.str_replace(' ', '_', strtolower($academicYear->name)).'_'.date('Y-m-d').'.xlsx';

            response()->streamDownload(function () {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Headers
                $headers = ['Member Name', 'Member ID', 'Group'];
                foreach ($this->months as $monthNum => $monthName) {
                    $headers[] = $monthName;
                }
                $headers[] = 'Total';
                $sheet->fromArray($headers, null, 'A1');

                // Data
                $row = 2;
                foreach ($this->members as $member) {
                    $rowData = [
                        $member->first_name.' '.$member->father_name,
                        $member->id,
                        $member->currentGroupAssignment?->group_id ? MemberGroup::find($member->currentGroupAssignment->group_id)?->name : 'N/A',
                    ];

                    $total = 0;
                    foreach (range(1, 12) as $month) {
                        $amount = $this->grid[$member->id][$month] ?? false
                            ? $this->getMemberGroupAmount($member, $month)
                            : 0;
                        $rowData[] = $amount > 0 ? 'Birr '.number_format($amount, 2) : '-';
                        $total += $amount;
                    }
                    $rowData[] = 'Birr '.number_format($total, 2);

                    $sheet->fromArray($rowData, null, 'A'.$row);
                    $row++;
                }

                // Auto-size columns
                foreach (range('A', 'Z') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save('php://output');
            }, $filename);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('An error occurred while exporting: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Refresh data from the database
     */
    public function refreshData(): void
    {
        $this->loadMembersWithFilters();
        $this->loadGrid();

        Notification::make()
            ->title('Data Refreshed')
            ->body('Contribution matrix data has been refreshed.')
            ->success()
            ->send();
    }

    /**
     * Mark all members as paid for a specific month
     */
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
                $this->autoSaveToggle($member->id, $month);
                $count++;
            }
        }

        Notification::make()
            ->title('Mass Update Complete')
            ->body("Marked {$count} members as paid for {$this->months[$month]}.")
            ->success()
            ->send();
    }

    /**
     * Mark all members as unpaid for a specific month
     */
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
                $this->autoSaveToggle($member->id, $month);
                $count++;
            }
        }

        Notification::make()
            ->title('Mass Update Complete')
            ->body("Unmarked {$count} members for {$this->months[$month]}.")
            ->success()
            ->send();
    }
}
