<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Models\MemberGroup;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class CollectionRateByGroupWidget extends Widget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Collection Rate by Group';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return [
                'groupRates' => [],
                'hasData' => false,
            ];
        }

        $groups = MemberGroup::active()->get();
        $groupRates = [];

        foreach ($groups as $group) {
            // Calculate expected amount for this group
            $groupMembers = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->where('member_group_id', $group->id)
                ->get();

            $totalExpected = 0;
            foreach ($groupMembers as $member) {
                $months = EthiopianDateHelper::getMonthsForContribution();
                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $group->id)
                        ->forMonth($monthName)
                        ->active()
                        ->value('amount') ?? 0;
                    $totalExpected += $expectedAmount;
                }
            }

            // Calculate collected amount for this group
            $totalCollected = Contribution::where('academic_year_id', $activeYear->id)
                ->whereHas('member', function ($query) use ($group) {
                    $query->where('member_group_id', $group->id);
                })
                ->notArchived()
                ->sum('amount');

            $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

            $groupRates[] = [
                'groupName' => $group->name,
                'totalExpected' => $totalExpected,
                'totalCollected' => $totalCollected,
                'collectionRate' => round($collectionRate, 2),
                'memberCount' => $groupMembers->count(),
            ];
        }

        // Sort by collection rate descending
        usort($groupRates, function ($a, $b) {
            return $b['collectionRate'] <=> $a['collectionRate'];
        });

        return [
            'groupRates' => $groupRates,
            'hasData' => !empty($groupRates),
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
        return view('filament.widgets.collection-rate-by-group-widget', $this->getViewData());
    }
}

