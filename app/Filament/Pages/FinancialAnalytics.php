<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\AidDistribution;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\MemberGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FinancialAnalytics extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Financial Analytics';

    protected static ?string $title = 'Comprehensive Financial Analytics';

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Reports';
    }

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.financial-analytics';

    public ?int $academic_year_id = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?int $group_id = null;

    public array $analyticsData = [];

    public array $charts = [];

    public array $academicYears = [];

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('page.financial.analytics');
    }

    public function mount(): void
    {
        $this->academicYears = AcademicYear::where('status', 'Active')
            ->orWhere('status', 'Inactive')
            ->orderBy('name', 'desc')
            ->pluck('name', 'id')
            ->toArray();

        $activeYear = AcademicYear::where('status', 'Active')->first();
        if ($activeYear) {
            $this->academic_year_id = $activeYear->id;
        }

        // Default to current year
        $this->start_date = now()->startOfYear()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');

        $this->loadAnalytics();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->columns(4)
            ->schema([
            Select::make('academic_year_id')
                ->label('Academic Year')
                ->options($this->academicYears)
                ->placeholder('Select Academic Year')
                ->live()
                ->afterStateUpdated(fn () => $this->loadAnalytics()),

            Select::make('group_id')
                ->label('Member Group')
                ->options(MemberGroup::pluck('name', 'id'))
                ->placeholder('All Groups')
                ->live()
                ->afterStateUpdated(fn () => $this->loadAnalytics()),

            DatePicker::make('start_date')
                ->label('Start Date')
                ->live()
                ->afterStateUpdated(fn () => $this->loadAnalytics()),

            DatePicker::make('end_date')
                ->label('End Date')
                ->live()
                ->afterStateUpdated(fn () => $this->loadAnalytics()),
        ]);
    }

    public function loadAnalytics(): void
    {
        if (!$this->academic_year_id) {
            $this->analyticsData = [];
        $this->charts = [
            'revenue_trend' => $this->getRevenueTrendChart(),
            'group_comparison' => $this->getGroupComparisonChart(),
            'monthly_distribution' => $this->getMonthlyDistributionChart(),
        ];
            return;
        }

        $this->analyticsData = [
            'overview' => $this->getOverviewStats(),
            'trends' => $this->getTrendsData(),
            'financial_trends' => $this->getFinancialTrends(),
            'group_performance' => $this->getGroupPerformance(),
            'monthly_breakdown' => $this->getMonthlyBreakdown(),
        ];

        $this->charts = [];
    }

    protected function getOverviewStats(): array
    {
        $contributionsQuery = Contribution::where('academic_year_id', $this->academic_year_id)
            ->where('is_paid', true);

        $donationsQuery = Donation::query();

        $incomeQuery = FinancialTransaction::income()->approved();
        $expenseQuery = FinancialTransaction::expense()->approved();
        $aidQuery = AidDistribution::query();

        if ($this->start_date && $this->end_date) {
            $contributionsQuery->whereBetween('payment_date', [$this->start_date, $this->end_date]);
            $donationsQuery->whereBetween('donation_date', [$this->start_date, $this->end_date]);
            $incomeQuery->whereBetween('transaction_date', [$this->start_date, $this->end_date]);
            $expenseQuery->whereBetween('transaction_date', [$this->start_date, $this->end_date]);
            $aidQuery->whereBetween('distribution_date', [$this->start_date, $this->end_date]);
        }

        if ($this->group_id) {
            $contributionsQuery->whereHas('member.currentGroupAssignment', function (Builder $query) {
                $query->where('group_id', $this->group_id);
            });
        }

        $totalContributions = $contributionsQuery->sum('amount');
        $totalDonations = $donationsQuery->sum('amount');
        $totalIncome = $incomeQuery->sum('amount');
        $totalExpenses = $expenseQuery->sum('amount');
        $totalAid = $aidQuery->sum('amount');
        $grandTotal = $totalContributions + $totalDonations + $totalIncome;
        $totalAllExpenses = $totalExpenses + $totalAid;
        $netIncome = $grandTotal - $totalAllExpenses;

        $activeMembers = Member::where('status', 'Active')
            ->when($this->group_id, function ($query) {
                $query->whereHas('currentGroupAssignment', function (Builder $query) {
                    $query->where('group_id', $this->group_id);
                });
            })
            ->count();

        $contributingMembers = $contributionsQuery->distinct('member_id')->count();
        $incomeGrowth = $this->calculateFinancialGrowth($incomeQuery);
        $expenseGrowth = $this->calculateFinancialGrowth($expenseQuery);

        return [
            'total_revenue' => $grandTotal,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_aid' => $totalAid,
            'total_all_expenses' => $totalAllExpenses,
            'net_income' => $netIncome,
            'total_contributions' => $totalContributions,
            'total_donations' => $totalDonations,
            'active_members' => $activeMembers,
            'contributing_members' => $contributingMembers,
            'participation_rate' => $activeMembers > 0 ? round(($contributingMembers / $activeMembers) * 100, 1) : 0,
            'average_contribution' => $contributingMembers > 0 ? round($totalContributions / $contributingMembers, 2) : 0,
            'revenue_growth' => $this->calculateRevenueGrowth(),
            'income_growth' => $incomeGrowth,
            'expense_growth' => $expenseGrowth,
        ];
    }

    protected function getTrendsData(): array
    {
        $monthlyData = Contribution::where('academic_year_id', $this->academic_year_id)
            ->where('is_paid', true)
            ->when($this->start_date && $this->end_date, function ($query) {
                $query->whereBetween('payment_date', [$this->start_date, $this->end_date]);
            })
            ->when($this->group_id, function ($query) {
                $query->whereHas('member.currentGroupAssignment', function (Builder $query) {
                    $query->where('group_id', $this->group_id);
                });
            })
            ->selectRaw('MONTH(payment_date) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupByRaw('MONTH(payment_date)')
            ->orderBy('month')
            ->get();

        return [
            'monthly_trends' => $monthlyData->toArray(),
            'growth_rate' => $this->calculateGrowthRate($monthlyData),
            'seasonal_patterns' => $this->identifySeasonalPatterns($monthlyData),
        ];
    }

    protected function getGroupPerformance(): array
    {
        $groupPerformance = MemberGroup::with(['members.contributions' => function ($query) {
            $query->where('academic_year_id', $this->academic_year_id)
                ->where('is_paid', true)
                ->when($this->start_date && $this->end_date, function ($query) {
                    $query->whereBetween('payment_date', [$this->start_date, $this->end_date]);
                });
        }])
            ->get()
            ->map(function ($group) {
                $totalContributions = $group->members->flatMap->contributions->sum('amount');
                $memberCount = $group->members->count();
                $contributingMembers = $group->members->flatMap->contributions->pluck('member_id')->unique()->count();

                return [
                    'group_name' => $group->name,
                    'total_contributions' => $totalContributions,
                    'member_count' => $memberCount,
                    'contributing_members' => $contributingMembers,
                    'participation_rate' => $memberCount > 0 ? round(($contributingMembers / $memberCount) * 100, 1) : 0,
                    'average_per_member' => $contributingMembers > 0 ? round($totalContributions / $contributingMembers, 2) : 0,
                ];
            });

        return $groupPerformance->sortByDesc('total_contributions')->values()->toArray();
    }

    protected function getMonthlyBreakdown(): array
    {
        return Contribution::where('academic_year_id', $this->academic_year_id)
            ->where('is_paid', true)
            ->when($this->start_date && $this->end_date, function ($query) {
                $query->whereBetween('payment_date', [$this->start_date, $this->end_date]);
            })
            ->when($this->group_id, function ($query) {
                $query->whereHas('member.currentGroupAssignment', function (Builder $query) {
                    $query->where('group_id', $this->group_id);
                });
            })
            ->selectRaw('month_name, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('month_name')
            ->orderByRaw('FIELD(month_name, "Meskerem", "Tikimt", "Hidar", "Tahsas", "Tir", "Yekatit", "Megabit", "Miazia", "Ginbot", "Sene", "Hamle", "Nehasse")')
            ->get()
            ->toArray();
    }

    protected function getFinancialTrends(): array
    {
        $incomeQuery = FinancialTransaction::income()->approved();
        $expenseQuery = FinancialTransaction::expense()->approved();

        if ($this->start_date && $this->end_date) {
            $incomeQuery->whereBetween('transaction_date', [$this->start_date, $this->end_date]);
            $expenseQuery->whereBetween('transaction_date', [$this->start_date, $this->end_date]);
        }

        $incomeMonthly = $incomeQuery->selectRaw('MONTH(transaction_date) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupByRaw('MONTH(transaction_date)')
            ->orderBy('month')
            ->get();

        $expenseMonthly = $expenseQuery->selectRaw('MONTH(transaction_date) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupByRaw('MONTH(transaction_date)')
            ->orderBy('month')
            ->get();

        $categories = FinancialTransaction::selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->when($this->start_date && $this->end_date, function ($q) {
                $q->whereBetween('transaction_date', [$this->start_date, $this->end_date]);
            })
            ->groupBy('category')
            ->get();

        return [
            'income_monthly' => $incomeMonthly->toArray(),
            'expense_monthly' => $expenseMonthly->toArray(),
            'categories' => $categories->toArray(),
        ];
    }

    protected function calculateFinancialGrowth($query): float
    {
        $clone = clone $query;
        $current = $clone->sum('amount');

        $previousStart = $this->start_date ? date('Y-m-d', strtotime($this->start_date . ' -1 year')) : now()->subYear()->startOfYear()->format('Y-m-d');
        $previousEnd = $this->end_date ? date('Y-m-d', strtotime($this->end_date . ' -1 year')) : now()->subYear()->format('Y-m-d');

        $previous = (clone $query)
            ->whereBetween('transaction_date', [$previousStart, $previousEnd])
            ->sum('amount');

        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
    }

    protected function getRevenueTrendChart(): array
    {
        $data = $this->getTrendsData()['monthly_trends'];

        return [
            'type' => 'line',
            'data' => array_map(function ($item) {
                return [
                    'month' => date('M', mktime(0, 0, 0, $item['month'], 1)),
                    'revenue' => $item['total'],
                ];
            }, $data),
        ];
    }

    protected function getGroupComparisonChart(): array
    {
        $data = $this->getGroupPerformance();

        return [
            'type' => 'bar',
            'data' => array_map(function ($item) {
                return [
                    'group' => $item['group_name'],
                    'contributions' => $item['total_contributions'],
                ];
            }, $data),
        ];
    }

    protected function getMonthlyDistributionChart(): array
    {
        $data = $this->getMonthlyBreakdown();

        return [
            'type' => 'pie',
            'data' => array_map(function ($item) {
                return [
                    'month' => $item['month_name'],
                    'amount' => $item['total'],
                ];
            }, $data),
        ];
    }

    protected function calculateRevenueGrowth(): float
    {
        // Compare current period with previous period
        $currentPeriod = Contribution::where('academic_year_id', $this->academic_year_id)
            ->where('is_paid', true)
            ->when($this->start_date && $this->end_date, function ($query) {
                $query->whereBetween('payment_date', [$this->start_date, $this->end_date]);
            })
            ->sum('amount');

        $previousPeriodStart = $this->start_date ?
            date('Y-m-d', strtotime($this->start_date . ' -1 year')) :
            date('Y-m-d', strtotime('-1 year'));

        $previousPeriodEnd = $this->end_date ?
            date('Y-m-d', strtotime($this->end_date . ' -1 year')) :
            date('Y-m-d', strtotime('-1 year'));

        $previousPeriod = Contribution::where('academic_year_id', $this->academic_year_id)
            ->where('is_paid', true)
            ->whereBetween('payment_date', [$previousPeriodStart, $previousPeriodEnd])
            ->sum('amount');

        return $previousPeriod > 0 ?
            round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 1) : 0;
    }

    protected function calculateGrowthRate($monthlyData): float
    {
        if (count($monthlyData) < 2) {
            return 0;
        }

        $firstMonth = $monthlyData->first();
        $lastMonth = $monthlyData->last();

        return $firstMonth['total'] > 0 ?
            round((($lastMonth['total'] - $firstMonth['total']) / $firstMonth['total']) * 100, 1) : 0;
    }

    protected function identifySeasonalPatterns($monthlyData): array
    {
        // Identify peak and low contribution months
        $averages = [];
        foreach ($monthlyData as $month) {
            $monthNum = $month['month'];
            $averages[$monthNum] = $month['total'];
        }

        if (empty($averages)) {
            return [];
        }

        $maxAmount = max($averages);
        $minAmount = min($averages);
        $avgAmount = array_sum($averages) / count($averages);

        $peakMonths = array_keys($averages, $maxAmount);
        $lowMonths = array_keys($averages, $minAmount);

        return [
            'peak_months' => $peakMonths,
            'low_months' => $lowMonths,
            'average_monthly' => $avgAmount,
            'variance' => $maxAmount - $minAmount,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
