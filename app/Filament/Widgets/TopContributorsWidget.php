<?php

namespace App\Filament\Widgets;

use App\Models\AcademicYear;
use App\Models\Contribution;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TopContributorsWidget extends Widget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Top 5 Contributors This Year';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return [
                'topContributors' => [],
                'hasData' => false,
            ];
        }

        $topContributors = Contribution::with(['member.memberGroup'])
            ->where('academic_year_id', $activeYear->id)
            ->notArchived()
            ->orderByRaw('SUM(amount) DESC')
            ->groupBy('member_id')
            ->limit(5)
            ->get()
            ->map(function ($contribution) {
                $totalAmount = Contribution::where('member_id', $contribution->member_id)
                    ->where('academic_year_id', $activeYear->id)
                    ->notArchived()
                    ->sum('amount');

                return [
                    'member' => $contribution->member,
                    'total' => $totalAmount,
                    'count' => Contribution::where('member_id', $contribution->member_id)
                        ->where('academic_year_id', $activeYear->id)
                        ->notArchived()
                        ->count(),
                ];
            });

        return [
            'topContributors' => $topContributors,
            'hasData' => $topContributors->isNotEmpty(),
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
        return view('filament.widgets.top-contributors-widget', $this->getViewData());
    }
}

