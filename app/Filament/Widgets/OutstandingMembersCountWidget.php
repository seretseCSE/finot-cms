<?php

namespace App\Filament\Widgets;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class OutstandingMembersCountWidget extends Widget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Outstanding Members Summary';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return [
                'totalMembers' => 0,
                'membersWithOutstanding' => 0,
                'totalOutstanding' => 0,
                'hasData' => false,
                'yearName' => 'No Active Year',
            ];
        }

        $members = Member::query()
            ->whereIn('status', ['Active', 'Member'])
            ->whereHas('memberGroup')
            ->get();

        $membersWithOutstanding = 0;
        $totalOutstanding = 0;

        foreach ($members as $member) {
            $months = EthiopianDateHelper::getMonthsForContribution();
            $memberOutstanding = 0;

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
                $memberOutstanding += max(0, $outstanding);
            }

            if ($memberOutstanding > 0) {
                $membersWithOutstanding++;
                $totalOutstanding += $memberOutstanding;
            }
        }

        return [
            'totalMembers' => $members->count(),
            'membersWithOutstanding' => $membersWithOutstanding,
            'totalOutstanding' => $totalOutstanding,
            'hasData' => true,
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
        return view('filament.widgets.outstanding-members-count-widget', $this->getViewData());
    }
}

