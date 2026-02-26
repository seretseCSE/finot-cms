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
        
        $query = Contribution::query();

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

        // Calculate metrics
        $totalExpected = 0;
        $totalCollected = $contributions->sum('amount');
        $totalOutstanding = 0;
        $collectionRate = 0;
        $topContributors = [];

        if (!empty($filters['academic_year_id']) && $filters['academic_year_id'] !== 'all') {
            // Calculate expected amounts for selected academic year
            $members = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->whereHas('memberGroup')
                ->when(!empty($filters['group_ids']), function ($q) use ($filters) {
                    $q->whereIn('member_group_id', $filters['group_ids']);
                })
                ->get();

            foreach ($members as $member) {
                $months = !empty($filters['months']) ? $filters['months'] : array_keys(EthiopianDateHelper::getMonthsForContribution());
                
                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                        ->forMonth($monthName)
                        ->active()
                        ->value('amount') ?? 0;
                    
                    $totalExpected += $expectedAmount;
                }
            }

            $totalOutstanding = $totalExpected - $totalCollected;
            $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

            // Get top 5 contributors
            $topContributors = $contributions
                ->groupBy('member_id')
                ->map(function ($group) {
                    return [
                        'member' => $group->first()->member,
                        'total' => $group->sum('amount'),
                    ];
                })
                ->sortByDesc('total')
                ->take(5)
                ->values();
        }

        return [
            'totalExpected' => $totalExpected,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'collectionRate' => round($collectionRate, 2),
            'topContributors' => $topContributors,
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
        return view('filament.widgets.contribution-report-metrics-widget', $this->getViewData());
    }
}

