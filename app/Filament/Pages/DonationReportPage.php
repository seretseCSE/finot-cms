<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use App\Models\Donation;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

class DonationReportPage extends Page
{
    use InteractsWithForms;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    public static function getNavigationLabel(): string
    {
        return 'Donation Report';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Reports';
    }

    protected string $view = 'filament.pages.donation-report';

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->can('page.report.donation');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dateFrom' => now()->startOfYear()->format('Y-m-d'),
            'dateTo' => now()->format('Y-m-d'),
            'selectedTypes' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getFormSchema())
            ->statePath('data');
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getReportData(): array
    {
        $filters = $this->form->getState();
        $dateFrom = $filters['dateFrom'] ?? null;
        $dateTo = $filters['dateTo'] ?? null;
        $selectedTypes = $filters['selectedTypes'] ?? [];

        $query = Donation::with(['recordedBy']);

        // Apply date range filter
        if ($dateFrom) {
            $query->whereDate('donation_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('donation_date', '<=', $dateTo);
        }

        // Apply donation type filter
        if (! empty($selectedTypes)) {
            $query->whereIn('donation_type', $selectedTypes);
        }

        $donations = $query->orderBy('donation_date', 'desc')->get();

        // Calculate metrics
        $totalDonated = $donations->sum('amount');
        $totalThisYear = $donations
            ->where('donation_date', '>=', now()->startOfYear())
            ->where('donation_date', '<', now()->endOfYear())
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
        $state = $this->form->getState();

        $this->validate([
            'data.dateFrom' => 'required|date',
            'data.dateTo' => 'required|date|after_or_equal:data.dateFrom',
            'data.selectedTypes' => 'array',
        ]);

        $this->data = $state;
    }

    public function resetFilters(): void
    {
        $this->form->fill([
            'dateFrom' => now()->startOfYear()->format('Y-m-d'),
            'dateTo' => now()->format('Y-m-d'),
            'selectedTypes' => [],
        ]);
    }
}
