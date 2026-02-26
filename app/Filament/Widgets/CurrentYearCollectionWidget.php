<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class CurrentYearCollectionWidget extends Widget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Current Year Collection Progress';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return [
                'totalExpected' => 0,
                'totalCollected' => 0,
                'totalOutstanding' => 0,
                'collectionRate' => 0,
                'yearName' => 'No Active Year',
            ];
        }

        // Get all active members with groups
        $members = Member::query()
            ->whereIn('status', ['Active', 'Member'])
            ->whereHas('memberGroup')
            ->get();

        $totalExpected = 0;
        $totalCollected = 0;

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

        $totalCollected = Contribution::where('academic_year_id', $activeYear->id)
            ->notArchived()
            ->sum('amount');

        $totalOutstanding = $totalExpected - $totalCollected;
        $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

        return [
            'totalExpected' => $totalExpected,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'collectionRate' => round($collectionRate, 2),
            'yearName' => $activeYear->name,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'data' => $this->getData(),
            'isCharityHead' => Auth::user()?->hasRole(['charity_head']),
        ];
    }

    public function view(): string
    {
        return view('filament.widgets.current-year-collection-widget', $this->getViewData());
    }
}

