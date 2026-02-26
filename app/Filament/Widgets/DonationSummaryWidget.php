<?php

namespace App\Filament\Widgets;

use App\Models\Donation;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DonationSummaryWidget extends Widget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Donation Summary';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    protected function getData(): array
    {
        $totalDonated = Donation::sum('amount');
        $totalThisYear = Donation::whereYear('donation_date', now()->year)->sum('amount');
        $lastDonation = Donation::latest('donation_date')->first();

        return [
            'totalDonated' => $totalDonated,
            'totalThisYear' => $totalThisYear,
            'lastDonation' => $lastDonation,
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
        return view('filament.widgets.donation-summary-widget', $this->getViewData());
    }
}

