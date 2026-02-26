<?php

namespace App\Filament\Widgets;

use App\Models\Donation;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DonationReportMetricsWidget extends Widget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Donation Metrics';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        // Get filter parameters from session or request
        $filters = request()->get('tableFilters', []);
        
        $query = Donation::query();

        // Apply same filters as the main report
        if (!empty($filters['date_from'])) {
            $query->whereDate('donation_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('donation_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['donation_types'])) {
            $query->whereIn('donation_type', $filters['donation_types']);
        }

        $donations = $query->get();

        // Calculate metrics
        $totalDonated = $donations->sum('amount');
        $totalThisYear = $donations->whereYear('donation_date', now()->year)->sum('amount');
        
        $totalByType = $donations
            ->groupBy('donation_type')
            ->map(function ($group) {
                return [
                    'type' => $group->first()->formatted_donation_type,
                    'total' => $group->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $lastDonation = $donations->max('donation_date');

        return [
            'totalDonated' => $totalDonated,
            'totalThisYear' => $totalThisYear,
            'totalByType' => $totalByType,
            'lastDonation' => $lastDonation,
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
        return view('filament.widgets.donation-report-metrics-widget', $this->getViewData());
    }
}

