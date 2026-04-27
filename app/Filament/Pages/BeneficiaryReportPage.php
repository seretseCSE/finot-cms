<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use App\Models\AidDistribution;
use App\Models\Beneficiary;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Forms\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

class BeneficiaryReportPage extends Page
{
    use InteractsWithForms;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Beneficiary Report';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    protected string $view = 'filament.pages.beneficiary-report';

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dateFrom' => now()->startOfYear()->format('Y-m-d'),
            'dateTo' => now()->format('Y-m-d'),
            'status' => [],
            'type' => [],
            'needCategory' => [],
        ]);
    }

    public function form(Schema $schema): Schemas\Form
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
        $status = $filters['status'] ?? [];
        $type = $filters['type'] ?? [];
        $needCategory = $filters['needCategory'] ?? [];

        $beneficiaryQuery = Beneficiary::query();

        if (! empty($status)) {
            $beneficiaryQuery->whereIn('status', $status);
        }

        if (! empty($type)) {
            $beneficiaryQuery->whereIn('type', $type);
        }

        if (! empty($needCategory)) {
            $beneficiaryQuery->whereIn('need_category', $needCategory);
        }

        $beneficiaries = $beneficiaryQuery->orderBy('full_name')->get();

        $aidQuery = AidDistribution::query();

        if ($dateFrom) {
            $aidQuery->whereDate('distribution_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $aidQuery->whereDate('distribution_date', '<=', $dateTo);
        }

        $aidDistributions = $aidQuery->with(['beneficiary', 'distributedBy'])->orderBy('distribution_date', 'desc')->get();

        $totalAidDistributed = $aidDistributions->sum('amount');
        $totalDistributions = $aidDistributions->count();
        $activeBeneficiaries = Beneficiary::where('status', 'Active')->count();
        $totalBeneficiaries = Beneficiary::count();

        $aidByType = $aidDistributions
            ->groupBy('aid_type')
            ->map(function ($group) {
                return [
                    'type' => $group->first()->aid_type,
                    'total' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $monthlyTrend = $aidDistributions
            ->groupBy(function ($item) {
                return $item->distribution_date->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->distribution_date->format('M Y'),
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })
            ->sortBy('month')
            ->values();

        return [
            'beneficiaries' => $beneficiaries,
            'aidDistributions' => $aidDistributions,
            'totalAidDistributed' => $totalAidDistributed,
            'totalDistributions' => $totalDistributions,
            'activeBeneficiaries' => $activeBeneficiaries,
            'totalBeneficiaries' => $totalBeneficiaries,
            'aidByType' => $aidByType,
            'monthlyTrend' => $monthlyTrend,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'reportData' => $this->getReportData(),
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

                    Grid::make(3)
                        ->schema([
                            Forms\Components\CheckboxList::make('status')
                                ->label('Status')
                                ->options([
                                    'Active' => 'Active',
                                    'Inactive' => 'Inactive',
                                    'Completed' => 'Completed',
                                ])
                                ->columns(3)
                                ->afterStateUpdated(fn () => $this->resetPage()),

                            Forms\Components\CheckboxList::make('type')
                                ->label('Type')
                                ->options([
                                    'Individual' => 'Individual',
                                    'Family' => 'Family',
                                    'Organization' => 'Organization',
                                ])
                                ->columns(3)
                                ->afterStateUpdated(fn () => $this->resetPage()),

                            Forms\Components\CheckboxList::make('needCategory')
                                ->label('Need Category')
                                ->options([
                                    'Food' => 'Food',
                                    'Medical' => 'Medical',
                                    'Education' => 'Education',
                                    'Housing' => 'Housing',
                                    'Other' => 'Other',
                                ])
                                ->columns(3)
                                ->afterStateUpdated(fn () => $this->resetPage()),
                        ]),
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
            'data.status' => 'array',
            'data.type' => 'array',
            'data.needCategory' => 'array',
        ]);

        $this->data = $state;
    }

    public function resetFilters(): void
    {
        $this->form->fill([
            'dateFrom' => now()->startOfYear()->format('Y-m-d'),
            'dateTo' => now()->format('Y-m-d'),
            'status' => [],
            'type' => [],
            'needCategory' => [],
        ]);
    }
}
