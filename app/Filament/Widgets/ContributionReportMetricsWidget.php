<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ContributionReportMetricsWidget extends Widget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Contribution Metrics';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        // Get filter parameters from session or request
        $filters = request()->get('tableFilters', []);
        
        $query = Contribution::with(['member.memberGroup']);

        // Apply same filters as the main report
        if (!empty($filters['academic_year_id']) && $filters['academic_year_id'] !== 'all') {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->whereHas('member.memberGroup', function ($q) use ($filters) {
                $q->where('member_groups.id', $filters['group_id']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        $contributions = $query->get();

        // Calculate metrics
        $totalExpected = 0;
        $totalCollected = $contributions->sum('amount');
        $totalOutstanding = 0;
        $collectionRate = 0;

        // Calculate expected amounts for current academic year
        if (empty($filters['academic_year_id']) || $filters['academic_year_id'] === 'all') {
            $currentYear = AcademicYear::where('is_active', true)->first();
            if ($currentYear) {
                $members = Member::query()
                    ->whereIn('status', ['Active', 'Member'])
                    ->whereHas('memberGroup')
                    ->when(!empty($filters['group_id']), function ($q) use ($filters) {
                        $q->where('member_group_id', $filters['group_id']);
                    })
                    ->get();

                foreach ($members as $member) {
                    $months = EthiopianDateHelper::getMonthsForContribution();
                    
                    foreach ($months as $monthName) {
                        $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                            ->forMonth($monthName)
                            ->active()
                            ->value('amount') ?? 0;
                        
                        $totalExpected += $expectedAmount;
                    }
                }
            }
        } else {
            // For specific academic year calculation
            $yearId = $filters['academic_year_id'];
            $members = Member::query()
                ->whereHas('memberGroupAssignments', function ($q) use ($yearId) {
                    $q->where('academic_year_id', $yearId)
                      ->whereNull('effective_to');
                })
                ->when(!empty($filters['group_id']), function ($q) use ($filters) {
                    $q->whereHas('memberGroupAssignments', function ($q) use ($filters) {
                        $q->where('group_id', $filters['group_id']);
                    });
                })
                ->get();

            foreach ($members as $member) {
                $months = EthiopianDateHelper::getMonthsForContribution();
                
                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                        ->forMonth($monthName)
                        ->active()
                        ->value('amount') ?? 0;
                    
                    $totalExpected += $expectedAmount;
                }
            }
        }

        $totalOutstanding = $totalExpected - $totalCollected;
        $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

        // Get top contributors
        $topContributors = $contributions
            ->groupBy('member_id')
            ->map(function ($group) {
                return [
                    'member' => $group->first()->member,
                    'total' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(3)
            ->values();

        // Payment method breakdown
        $paymentMethods = $contributions
            ->groupBy('payment_method')
            ->map(function ($group) {
                return [
                    'method' => $group->first()->payment_method,
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                    'percentage' => $totalCollected > 0 ? (($group->sum('amount') / $totalCollected) * 100) : 0,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        // Month-over-month comparison
        $currentMonth = now()->format('F');
        $previousMonth = now()->subMonth()->format('F');
        
        $currentMonthContributions = $contributions->where('month_name', $currentMonth);
        $previousMonthContributions = $contributions->where('month_name', $previousMonth);
        
        $currentMonthAmount = $currentMonthContributions->sum('amount');
        $previousMonthAmount = $previousMonthContributions->sum('amount');
        $monthOverMonthChange = $previousMonthAmount > 0 ? (($currentMonthAmount - $previousMonthAmount) / $previousMonthAmount) * 100 : 0;

        return [
            'totalExpected' => $totalExpected,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'collectionRate' => round($collectionRate, 2),
            'topContributors' => $topContributors,
            'paymentMethods' => $paymentMethods,
            'currentMonthAmount' => $currentMonthAmount,
            'previousMonthAmount' => $previousMonthAmount,
            'monthOverMonthChange' => round($monthOverMonthChange, 2),
            'totalContributors' => $contributions->groupBy('member_id')->count(),
            'averageContribution' => $totalCollected > 0 ? $totalCollected / $contributions->groupBy('member_id')->count() : 0,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'data' => $this->getData(),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getCards(): array
    {
        $data = $this->getData();
        
        return [
            // Collection Rate Card
            \Filament\Widgets\StatsOverviewWidget\Stat::make('Collection Rate')
                ->value($data['collectionRate'] . '%')
                ->description($data['collectionRate'] >= 80 ? 'Good Performance' : ($data['collectionRate'] >= 60 ? 'Moderate' : 'Needs Improvement'))
                ->descriptionIcon($data['collectionRate'] >= 80 ? 'heroicon-m-arrow-trending-up' : ($data['collectionRate'] >= 60 ? 'heroicon-m-arrow-trending-right' : 'heroicon-m-arrow-trending-down'))
                ->color($data['collectionRate'] >= 80 ? 'success' : ($data['collectionRate'] >= 60 ? 'warning' : 'danger'))
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            // Total Collected Card
            \Filament\Widgets\StatsOverviewWidget\Stat::make('Total Collected')
                ->value(number_format($data['totalCollected'], 2))
                ->description('ETB')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->chart([12, 5, 15, 8, 20, 12, 25]),

            // Outstanding Amount Card
            \Filament\Widgets\StatsOverviewWidget\Stat::make('Outstanding')
                ->value(number_format($data['totalOutstanding'], 2))
                ->description('ETB')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($data['totalOutstanding'] > 0 ? 'warning' : 'success')
                ->chart([5, 8, 3, 12, 7, 15, 9]),

            // Total Contributors Card
            \Filament\Widgets\StatsOverviewWidget\Stat::make('Contributors')
                ->value($data['totalContributors'])
                ->description('Active Contributors')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->chart([10, 15, 12, 18, 20, 22, 25]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        $data = $this->getData();
        
        return [
            // Top Contributors Widget
            \Filament\Widgets\TableWidget::make()
                ->query(function () use ($data) {
                    return collect($data['topContributors']);
                })
                ->columns([
                    \Filament\Tables\Columns\TextColumn::make('member.full_name')
                        ->label('Top Contributors')
                        ->searchable(),
                    \Filament\Tables\Columns\TextColumn::make('total')
                        ->label('Amount')
                        ->formatStateUsing(fn ($state) => 'ETB ' . number_format($state, 2))
                        ->sortable(),
                    \Filament\Tables\Columns\TextColumn::make('count')
                        ->label('Contributions')
                        ->sortable(),
                ])
                ->heading('Top Contributors')
                ->paginated([5, 10, 25]),

            // Payment Methods Widget
            \Filament\Widgets\TableWidget::make()
                ->query(function () use ($data) {
                    return collect($data['paymentMethods']);
                })
                ->columns([
                    \Filament\Tables\Columns\TextColumn::make('method')
                        ->label('Payment Method')
                        ->searchable(),
                    \Filament\Tables\Columns\TextColumn::make('amount')
                        ->label('Amount')
                        ->formatStateUsing(fn ($state) => 'ETB ' . number_format($state, 2))
                        ->sortable(),
                    \Filament\Tables\Columns\TextColumn::make('percentage')
                        ->label('Percentage')
                        ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                        ->sortable(),
                ])
                ->heading('Payment Method Breakdown')
                ->paginated([5, 10, 25]),
        ];
    }

    public function view(): string
    {
        return view('filament.widgets.contribution-report-metrics-widget', $this->getViewData());
    }
}

