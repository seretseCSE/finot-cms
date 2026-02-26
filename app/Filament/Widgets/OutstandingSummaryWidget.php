<?php

namespace App\Filament\Widgets;

use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Helpers\EthiopianDateHelper;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class OutstandingSummaryWidget extends Widget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Outstanding Summary';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return [
                'total_expected' => 0,
                'total_collected' => 0,
                'total_outstanding' => 0,
                'collection_rate' => 0,
                'members_with_outstanding' => 0,
            ];
        }

        // Get all active members with groups
        $members = Member::query()
            ->whereIn('status', ['Active', 'Member'])
            ->whereHas('memberGroup')
            ->get();

        $totalExpected = 0;
        $totalCollected = 0;
        $membersWithOutstanding = 0;

        foreach ($members as $member) {
            $months = EthiopianDateHelper::getMonthsForContribution();

            foreach ($months as $monthName) {
                $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                    ->forMonth($monthName)
                    ->active()
                    ->value('amount') ?? 0;

                $paidAmount = Contribution::forMemberAndYear($member->id, $activeYear->id)
                    ->forMonth($monthName)
                    ->notArchived()
                    ->sum('amount') ?? 0;

                $outstanding = $expectedAmount - $paidAmount;

                $totalExpected += $expectedAmount;
                $totalCollected += $paidAmount;

                if ($outstanding > 0) {
                    $membersWithOutstanding++;
                }
            }
        }

        $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

        return [
            'total_expected' => $totalExpected,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalExpected - $totalCollected,
            'collection_rate' => round($collectionRate, 2),
            'members_with_outstanding' => $membersWithOutstanding,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'data' => $this->getData(),
            'activeYear' => AcademicYear::where('is_active', true)->first(),
        ];
    }

    public function view(): string
    {
        return view('filament.widgets.outstanding-summary-widget', $this->getViewData());
    }
}

