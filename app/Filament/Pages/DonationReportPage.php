<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DonationReportMetricsWidget;
use App\Filament\Widgets\DonationReportChartsWidget;
use App\Helpers\EthiopianDateHelper;
use App\Models\Donation;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class DonationReportPage extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-gift'; }

    public static function getNavigationLabel(): string { return 'Donation Report'; }

    public static function getNavigationGroup(): ?string { return 'Reports'; }

    protected string $view = 'filament.pages.donation-report';

    public static function getNavigationSort(): ?int { return 5; }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    public $dateFrom = null;
    public $dateTo = null;
    public $selectedTypes = [];

    public function mount(): void
    {
        // Set default date range to current year
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DonationReportMetricsWidget::class,
            DonationReportChartsWidget::class,
        ];
    }

    public function getReportData(): array
    {
        $query = Donation::with(['recordedBy']);

        // Apply date range filter
        if ($this->dateFrom) {
            $query->whereDate('donation_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('donation_date', '<=', $this->dateTo);
        }

        // Apply donation type filter
        if (!empty($this->selectedTypes)) {
            $query->whereIn('donation_type', $this->selectedTypes);
        }

        $donations = $query->orderBy('donation_date', 'desc')->get();

        // Calculate metrics
        $totalDonated = $donations->sum('amount');
        $totalThisYear = $donations
            ->whereYear('donation_date', now()->year)
            ->sum('amount');
        
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

        return [
            'donations' => $donations,
            'totalDonated' => $totalDonated,
            'totalThisYear' => $totalThisYear,
            'totalByType' => $totalByType,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'reportData' => $this->getReportData(),
            'donationTypes' => [
                'General Fund' => 'General Fund',
                'Building Fund' => 'Building Fund',
                'Missionary Support' => 'Missionary Support',
                'Charity/Aid' => 'Charity/Aid',
                'Other' => 'Other',
            ],
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Report Filters')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('dateFrom')
                                ->label('Date From')
                                ->default(now()->startOfYear()->format('Y-m-d'))
                                ->afterStateUpdated(fn () => $this->resetPage()),
                            
                            Forms\Components\DatePicker::make('dateTo')
                                ->label('Date To')
                                ->default(now()->format('Y-m-d'))
                                ->afterStateUpdated(fn () => $this->resetPage()),
                        ]),
                    
                    Forms\Components\CheckboxList::make('selectedTypes')
                        ->label('Donation Types')
                        ->options([
                            'General Fund' => 'General Fund',
                            'Building Fund' => 'Building Fund',
                            'Missionary Support' => 'Missionary Support',
                            'Charity/Aid' => 'Charity/Aid',
                            'Other' => 'Other',
                        ])
                        ->columns(3)
                        ->afterStateUpdated(fn () => $this->resetPage()),
                ])
                ->columns(1),
        ];
    }

    public function resetPage(): void
    {
        // Reset any pagination or state
    }

    public function applyFilters(): void
    {
        $this->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
            'selectedTypes' => 'array',
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo', 'selectedTypes']);
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }
}

