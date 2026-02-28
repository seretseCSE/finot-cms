<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OutstandingSummaryWidget;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Models\MemberGroup;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OutstandingContributionsPage extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-currency-dollar'; }

    public static function getNavigationLabel(): string { return 'Outstanding Contributions'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    protected string $view = 'filament.pages.outstanding-contributions';

    public static function getNavigationSort(): ?int { return 3; }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    public $activeYear;
    public $selectedGroup = null;
    public $selectedMonth = null;
    public $selectedClass = null;

    public function mount(): void
    {
        $this->activeYear = AcademicYear::where('is_active', true)->first();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OutstandingSummaryWidget::class,
        ];
    }

    /**
     * Calculate the aging bucket for an outstanding contribution month.
     * Maps Ethiopian months to approximate Gregorian dates for aging calculation.
     */
    protected function getAgingBucket(string $monthName): array
    {
        // Ethiopian months mapped to approximate Gregorian month indices
        $monthOrder = EthiopianDateHelper::getMonthsForContribution();
        $monthIndex = array_search($monthName, $monthOrder);

        if ($monthIndex === false) {
            return ['bucket' => 'Unknown', 'days' => 0];
        }

        // Each Ethiopian month starts roughly 30 days after the previous.
        // The expected payment date for month N is roughly the end of that month.
        // We calculate days overdue from current date to the end of that month.
        $yearStart = $this->activeYear->start_date ?? now()->startOfYear();
        if (is_string($yearStart)) {
            $yearStart = Carbon::parse($yearStart);
        }
        $expectedDate = $yearStart->copy()->addMonths($monthIndex + 1);
        $daysOverdue = max(0, (int) now()->diffInDays($expectedDate, false) * -1);

        if ($daysOverdue <= 0) {
            $bucket = 'Current';
        } elseif ($daysOverdue <= 30) {
            $bucket = '1-30 days';
        } elseif ($daysOverdue <= 60) {
            $bucket = '31-60 days';
        } elseif ($daysOverdue <= 90) {
            $bucket = '61-90 days';
        } else {
            $bucket = '90+ days';
        }

        return ['bucket' => $bucket, 'days' => $daysOverdue];
    }

    public function getTableData(): array
    {
        if (!$this->activeYear) {
            return [];
        }

        $query = Member::query()
            ->whereIn('status', ['Active', 'Member'])
            ->whereHas('memberGroup')
            ->with(['memberGroup', 'schoolClass']);

        // Apply filters
        if ($this->selectedGroup) {
            $query->where('member_group_id', $this->selectedGroup);
        }

        if ($this->selectedClass) {
            $query->where('school_class_id', $this->selectedClass);
        }

        $members = $query->get();

        $outstandingData = [];

        foreach ($members as $member) {
            // Get all 12 months for the active year
            $months = EthiopianDateHelper::getMonthsForContribution();

            foreach ($months as $monthName) {
                $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                    ->forMonth($monthName)
                    ->active()
                    ->value('amount') ?? 0;

                $paidAmount = Contribution::forMemberAndYear($member->id, $this->activeYear->id)
                    ->forMonth($monthName)
                    ->notArchived()
                    ->sum('amount') ?? 0;

                $outstanding = $expectedAmount - $paidAmount;

                // Only include if outstanding > 0
                if ($outstanding > 0) {
                    // Apply month filter if selected
                    if ($this->selectedMonth && $this->selectedMonth !== $monthName) {
                        continue;
                    }

                    // Calculate aging bucket
                    $aging = $this->getAgingBucket($monthName);

                    $outstandingData[] = [
                        'member' => $member,
                        'month' => $monthName,
                        'expected' => $expectedAmount,
                        'paid' => $paidAmount,
                        'outstanding' => $outstanding,
                        'aging_bucket' => $aging['bucket'],
                        'days_overdue' => $aging['days'],
                    ];
                }
            }
        }

        // Sort by days overdue descending (most overdue first)
        usort($outstandingData, function ($a, $b) {
            return $b['days_overdue'] <=> $a['days_overdue'];
        });

        return $outstandingData;
    }

    public function getSummaryData(): array
    {
        if (!$this->activeYear) {
            return [
                'total_expected' => 0,
                'total_collected' => 0,
                'total_outstanding' => 0,
                'collection_rate' => 0,
                'aging_30' => 0,
                'aging_60' => 0,
                'aging_90' => 0,
                'aging_over_90' => 0,
            ];
        }

        $data = $this->getTableData();

        $totalExpected = array_sum(array_column($data, 'expected'));
        $totalCollected = array_sum(array_column($data, 'paid'));
        $totalOutstanding = array_sum(array_column($data, 'outstanding'));
        $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

        // Aging summary: total outstanding amounts by bucket
        $aging30 = array_sum(array_map(fn ($r) => $r['aging_bucket'] === '1-30 days' ? $r['outstanding'] : 0, $data));
        $aging60 = array_sum(array_map(fn ($r) => $r['aging_bucket'] === '31-60 days' ? $r['outstanding'] : 0, $data));
        $aging90 = array_sum(array_map(fn ($r) => $r['aging_bucket'] === '61-90 days' ? $r['outstanding'] : 0, $data));
        $agingOver90 = array_sum(array_map(fn ($r) => $r['aging_bucket'] === '90+ days' ? $r['outstanding'] : 0, $data));

        return [
            'total_expected' => $totalExpected,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'collection_rate' => round($collectionRate, 2),
            'aging_30' => $aging30,
            'aging_60' => $aging60,
            'aging_90' => $aging90,
            'aging_over_90' => $agingOver90,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'activeYear' => $this->activeYear,
            'summaryData' => $this->getSummaryData(),
            'tableData' => $this->getTableData(),
        ];
    }

    public function refreshTable(): void
    {
        // This method will be called when filters are applied
        $this->validate([
            'selectedGroup' => 'nullable|integer|exists:member_groups,id',
            'selectedMonth' => 'nullable|string',
            'selectedClass' => 'nullable|integer|exists:school_classes,id',
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['selectedGroup', 'selectedMonth', 'selectedClass']);
    }

    public function resetPage(): void
    {
        // Reset any pagination or state
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Schemas\Components\Section::make('Filters')
                ->schema([
                    Forms\Components\Select::make('selectedGroup')
                        ->label('Member Group')
                        ->placeholder('All Groups')
                        ->options(MemberGroup::active()->pluck('name', 'id')->all())
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\Select::make('selectedMonth')
                        ->label('Month')
                        ->placeholder('All Months')
                        ->options(EthiopianDateHelper::getMonthsForContribution())
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\Select::make('selectedClass')
                        ->label('Class')
                        ->placeholder('All Classes')
                        ->relationship('schoolClass', 'name')
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->resetPage()),
                ])
                ->columns(3),
        ];
    }
}


