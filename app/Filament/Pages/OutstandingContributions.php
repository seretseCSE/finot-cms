<?php

namespace App\Filament\Pages;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Pages\Page;

class OutstandingContributions extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    protected static ?string $title = 'Outstanding Contributions';

    protected string $view = 'filament.pages.outstanding-contributions';

    public ?int $group_id = null;

    public ?int $month = null;

    public $activeYear;

    public $summaryData = [
        'total_expected' => 0,
        'total_collected' => 0,
        'total_outstanding' => 0,
        'collection_rate' => 0,
    ];

    public $tableData = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('superadmin') || $user?->hasRole('finance_head');
    }

    public function mount(): void
    {
        $this->activeYear = AcademicYear::where('status', 'Active')->first();

        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function updatedGroupId(): void
    {
        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function updatedMonth(): void
    {
        if ($this->activeYear) {
            $this->calculateData();
        }
    }

    public function resetFilters(): void
    {
        $this->group_id = null;
        $this->month = null;

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

                $totalExpected += $expected;
                $totalCollected += $paid;

                $outstanding = $expected - $paid;

                if ($outstanding > 0) {
                    $this->tableData[] = [
                        'member' => $member,
                        'month' => $m,
                        'month_name' => $monthName,
                        'expected' => $expected,
                        'paid' => $paid,
                        'outstanding' => $outstanding,
                    ];
                }
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
