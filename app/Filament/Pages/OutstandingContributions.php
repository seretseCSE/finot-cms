<?php

namespace App\Filament\Pages;


use App\Filament\Support\EmbeddableInHub;
use App\Filament\Support\HidesFromNavigation;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

class OutstandingContributions extends Page
{
    use EmbeddableInHub;
    use HidesFromNavigation;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Contributions';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    protected static ?string $title = 'Outstanding Contributions';

    protected string $view = 'filament.pages.outstanding-contributions';

    public $group_id = null;

    public $month = null;

    public $activeYear;

    public $summaryData = [
        'total_expected' => 0,
        'total_collected' => 0,
        'total_outstanding' => 0,
        'collection_rate' => 0,
    ];

    public $tableData = [];

    public $perPage = 10;

    public $page = 1;

    public array $ethiopianMonths = [
        1 => 'Meskerem',
        2 => 'Tikimt',
        3 => 'Hidar',
        4 => 'Tahsas',
        5 => 'Tir',
        6 => 'Yekatit',
        7 => 'Megabit',
        8 => 'Miazia',
        9 => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehasse',
        13 => 'Pagume',
    ];

    protected function getQueryString(): array
    {
        return ['page' => ['except' => 1]];
    }

    public function getTableDataPaginator(): LengthAwarePaginator
    {
        $page = max(1, (int) $this->page);
        $perPage = max(1, (int) $this->perPage);
        $total = count($this->tableData);

        return new LengthAwarePaginator(
            array_slice($this->tableData, ($page - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('reports.outstanding_view');
    }

    public function mount(): void
    {
        $this->activeYear = AcademicYear::where('status', 'Active')->first();

        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function updatedGroupId($value): void
    {
        $this->group_id = filled($value) ? (int) $value : null;
        $this->page = 1;
        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function updatedMonth($value): void
    {
        $this->month = filled($value) ? (int) $value : null;
        $this->page = 1;
        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function updatedPerPage(): void
    {
        $this->perPage = max(1, (int) $this->perPage);
        $this->page = 1;
        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function resetFilters(): void
    {
        $this->group_id = null;
        $this->month = null;
        $this->page = 1;

        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    protected function calculateData(): void
    {
        $membersQuery = Member::with(['currentGroupAssignment.group'])
            ->where('status', 'Active');

        if ($this->group_id) {
            $membersQuery->whereHas(
                'currentGroupAssignment',
                fn ($q) => $q->where('group_id', $this->group_id)
            );
        }

        $members = $membersQuery->get();

        $monthsToCalculate = $this->month
            ? [$this->month]
            : range(1, 12);

        $this->tableData = [];
        $totalExpected = 0;
        $totalCollected = 0;

        foreach ($members as $member) {
            $currentGroupId = $member->currentGroupAssignment?->group_id;

            if (! $currentGroupId) {
                continue;
            }

            $memberExpected = 0;
            $memberPaid = 0;
            $outstandingMonths = [];

            foreach ($monthsToCalculate as $m) {
                $monthName = EthiopianDateHelper::getEthiopianMonthName($m);

                $expected = ContributionAmount::where('group_id', $currentGroupId)
                    ->where('academic_year_id', $this->activeYear->id)
                    ->where('month_name', $monthName)
                    ->active()
                    ->first()?->amount ?? 0;

                if ($expected <= 0) {
                    continue;
                }

                $paid = Contribution::where('member_id', $member->id)
                    ->where('academic_year_id', $this->activeYear->id)
                    ->where('month_name', $monthName)
                    ->sum('amount');

                $memberExpected += $expected;
                $memberPaid += $paid;

                $monthOutstanding = $expected - $paid;
                if ($monthOutstanding > 0) {
                    $outstandingMonths[] = $monthName;
                }
            }

            $totalExpected += $memberExpected;
            $totalCollected += $memberPaid;

            $memberOutstanding = $memberExpected - $memberPaid;

            if ($memberOutstanding > 0) {
                $this->tableData[] = [
                    'member_id' => $member->id,
                    'member_name' => $member->full_name,
                    'member_code' => $member->member_code,
                    'group_name' => $member->currentGroupAssignment?->group?->name ?? 'Unassigned',
                    'month' => $this->month ? reset($monthsToCalculate) : null,
                    'month_name' => $this->month
                        ? EthiopianDateHelper::getEthiopianMonthName(reset($monthsToCalculate))
                        : implode(', ', $outstandingMonths),
                    'expected' => $memberExpected,
                    'paid' => $memberPaid,
                    'outstanding' => $memberOutstanding,
                    'is_annual' => is_null($this->month),
                ];
            }
        }

        $totalOutstanding = $totalExpected - $totalCollected;
        $totalOutstanding = max(0, $totalOutstanding);

        $collectionRate = $totalExpected > 0
            ? min(100, round(($totalCollected / $totalExpected) * 100, 1))
            : 0;

        $this->summaryData = [
            'total_expected' => $totalExpected,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'collection_rate' => $collectionRate,
        ];
    }
}
