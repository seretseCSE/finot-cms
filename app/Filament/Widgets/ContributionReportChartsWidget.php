<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\Contribution;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ContributionReportChartsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Contribution Charts';

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

        // Prepare chart data
        $collectionRateByGroup = [];
        $monthlyTrend = [];
        $paymentMethodDistribution = [];
        $topContributorsData = [];
        $monthOverMonthData = [];

        // Collection rate per group
        $groupContributions = $contributions->groupBy('member.memberGroup.name');
        foreach ($groupContributions as $groupName => $groupContributions) {
            $totalCollected = $groupContributions->sum('amount');
            $totalContributors = $groupContributions->groupBy('member_id')->count();
            
            $collectionRateByGroup[] = [
                'group' => $groupName,
                'amount' => $totalCollected,
                'contributors' => $totalContributors,
            ];
        }

        // Monthly trend (last 6 months)
        $months = EthiopianDateHelper::getMonthsForContribution();
        $lastSixMonths = array_slice($months, -6, 6, true);
        
        foreach ($lastSixMonths as $monthName) {
            $monthContributions = $contributions->where('month_name', $monthName);
            $monthlyTrend[] = [
                'month' => $monthName,
                'amount' => $monthContributions->sum('amount'),
                'count' => $monthContributions->count(),
                'contributors' => $monthContributions->groupBy('member_id')->count(),
            ];
        }

        // Payment method distribution
        $paymentMethods = $contributions->groupBy('payment_method');
        foreach ($paymentMethods as $method => $methodContributions) {
            $paymentMethodDistribution[] = [
                'method' => $method,
                'amount' => $methodContributions->sum('amount'),
                'count' => $methodContributions->count(),
            ];
        }

        // Top contributors data
        $topContributors = $contributions
            ->groupBy('member_id')
            ->map(function ($group) {
                return [
                    'member' => $group->first()->member->full_name,
                    'total' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        return [
            'collectionRateByGroup' => $collectionRateByGroup,
            'monthlyTrend' => $monthlyTrend,
            'paymentMethodDistribution' => $paymentMethodDistribution,
            'topContributors' => $topContributors,
        ];
    }

    protected function getCharts(): array
    {
        $data = $this->getData();
        
        return [
            // Monthly Trend Chart
            \Filament\Widgets\ChartWidget::make()
                ->type('line')
                ->data([
                    'labels' => collect($data['monthlyTrend'])->pluck('month')->toArray(),
                    'datasets' => [
                        [
                            'label' => 'Monthly Collections (ETB)',
                            'data' => collect($data['monthlyTrend'])->pluck('amount')->toArray(),
                            'borderColor' => 'rgb(59, 130, 246)',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                            'fill' => true,
                        ],
                        [
                            'label' => 'Number of Contributions',
                            'data' => collect($data['monthlyTrend'])->pluck('count')->toArray(),
                            'borderColor' => 'rgb(34, 197, 94)',
                            'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                            'fill' => true,
                            'yAxisID' => 'y1',
                        ],
                    ],
                ])
                ->options([
                    'responsive' => true,
                    'interaction' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ],
                    'scales' => [
                        'y' => [
                            'type' => 'linear',
                            'display' => true,
                            'position' => 'left',
                        ],
                        'y1' => [
                            'type' => 'linear',
                            'display' => true,
                            'position' => 'right',
                            'grid' => [
                                'drawOnChartArea' => false,
                            ],
                        ],
                    ],
                ])
                ->heading('Monthly Collection Trends'),

            // Payment Method Distribution Chart
            \Filament\Widgets\ChartWidget::make()
                ->type('doughnut')
                ->data([
                    'labels' => collect($data['paymentMethodDistribution'])->pluck('method')->toArray(),
                    'datasets' => [
                        [
                            'data' => collect($data['paymentMethodDistribution'])->pluck('amount')->toArray(),
                            'backgroundColor' => [
                                'rgb(239, 68, 68)',
                                'rgb(34, 197, 94)',
                                'rgb(59, 130, 246)',
                                'rgb(168, 85, 247)',
                                'rgb(251, 146, 60)',
                            ],
                        ],
                    ],
                ])
                ->options([
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [
                            'position' => 'bottom',
                        ],
                    ],
                ])
                ->heading('Payment Method Distribution'),

            // Group Performance Chart
            \Filament\Widgets\ChartWidget::make()
                ->type('bar')
                ->data([
                    'labels' => collect($data['collectionRateByGroup'])->pluck('group')->toArray(),
                    'datasets' => [
                        [
                            'label' => 'Total Collected (ETB)',
                            'data' => collect($data['collectionRateByGroup'])->pluck('amount')->toArray(),
                            'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                        ],
                        [
                            'label' => 'Number of Contributors',
                            'data' => collect($data['collectionRateByGroup'])->pluck('contributors')->toArray(),
                            'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                            'yAxisID' => 'y1',
                        ],
                    ],
                ])
                ->options([
                    'responsive' => true,
                    'scales' => [
                        'y' => [
                            'type' => 'linear',
                            'display' => true,
                            'position' => 'left',
                        ],
                        'y1' => [
                            'type' => 'linear',
                            'display' => true,
                            'position' => 'right',
                            'grid' => [
                                'drawOnChartArea' => false,
                            ],
                        ],
                    ],
                ])
                ->heading('Group Performance'),

            // Top Contributors Chart
            \Filament\Widgets\ChartWidget::make()
                ->type('horizontalBar')
                ->data([
                    'labels' => collect($data['topContributors'])->pluck('member')->toArray(),
                    'datasets' => [
                        [
                            'label' => 'Total Contributions (ETB)',
                            'data' => collect($data['topContributors'])->pluck('total')->toArray(),
                            'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                        ],
                    ],
                ])
                ->options([
                    'responsive' => true,
                    'indexAxis' => 'y',
                    'plugins' => [
                        'legend' => [
                            'display' => false,
                        ],
                    ],
                ])
                ->heading('Top 10 Contributors'),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'data' => $this->getData(),
        ];
    }

    public function view(): string
    {
        return view('filament.widgets.contribution-report-charts-widget', $this->getViewData());
    }
}

