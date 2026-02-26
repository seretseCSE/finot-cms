<?php

namespace App\Filament\Widgets;

use App\Models\AcademicYear;
use App\Models\Contribution;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class MonthlyTrendWidget extends Widget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Monthly Collection Trend (Last 6 Months)';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return [
                'monthlyData' => [],
                'hasData' => false,
            ];
        }

        // Get last 6 months of contribution data
        $monthlyData = Contribution::where('academic_year_id', $activeYear->id)
            ->notArchived()
            ->whereDate('payment_date', '>=', now()->subMonths(5)->startOfMonth())
            ->orderBy('payment_date')
            ->get()
            ->groupBy(function ($item) {
                return $item->payment_date->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->payment_date->format('M Y'),
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->values();

        return [
            'monthlyData' => $monthlyData,
            'hasData' => $monthlyData->isNotEmpty(),
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
        return view('filament.widgets.monthly-trend-widget', $this->getViewData());
    }
}

