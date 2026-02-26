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

        // Collection rate per group
        $groupContributions = $contributions->groupBy('member.member_group_id');
        foreach ($groupContributions as $groupId => $groupContributions) {
            $groupName = $groupContributions->first()->member->memberGroup->name ?? 'Unknown';
            $totalCollected = $groupContributions->sum('amount');
            
            $collectionRateByGroup[] = [
                'group' => $groupName,
                'amount' => $totalCollected,
            ];
        }

        // Monthly collection trend
        $monthlyContributions = $contributions
            ->groupBy(function ($item) {
                return $item->payment_date->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->payment_date->format('M Y'),
                    'amount' => $group->sum('amount'),
                ];
            })
            ->sortBy('month')
            ->values();

        // Payment method distribution
        $paymentMethodContributions = $contributions->groupBy('payment_method');
        foreach ($paymentMethodContributions as $method => $methodContributions) {
            $paymentMethodDistribution[] = [
                'method' => $method,
                'amount' => $methodContributions->sum('amount'),
            ];
        }

        return [
            'collectionRateByGroup' => array_values($collectionRateByGroup),
            'monthlyTrend' => array_values($monthlyTrend),
            'paymentMethodDistribution' => array_values($paymentMethodDistribution),
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

