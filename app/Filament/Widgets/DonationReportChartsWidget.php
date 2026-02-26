<?php

namespace App\Filament\Widgets;

use App\Models\Donation;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DonationReportChartsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Donation Charts';

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

        // Prepare chart data
        $donationsByType = [];
        $monthlyTrend = [];

        // Donations by type (pie chart)
        $typeDonations = $donations->groupBy('donation_type');
        foreach ($typeDonations as $type => $typeDonations) {
            $donationsByType[] = [
                'type' => $typeDonations->first()->formatted_donation_type,
                'amount' => $typeDonations->sum('amount'),
                'count' => $typeDonations->count(),
            ];
        }

        // Monthly donation trend (bar chart)
        $monthlyDonations = $donations
            ->groupBy(function ($item) {
                return $item->donation_date->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->donation_date->format('M Y'),
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->sortBy('month')
            ->values();

        return [
            'donationsByType' => array_values($donationsByType),
            'monthlyTrend' => array_values($monthlyDonations),
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
        return view('filament.widgets.donation-report-charts-widget', $this->getViewData());
    }
}

