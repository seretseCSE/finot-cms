<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ContributionReportMetricsWidget;
use App\Filament\Widgets\ContributionReportChartsWidget;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\SchoolClass;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContributionReportPage extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-document-text'; }

    public static function getNavigationLabel(): string { return 'Contribution Report'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    protected string $view = 'filament.pages.contribution-report';

    public static function getNavigationSort(): ?int { return 4; }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    public $selectedAcademicYear = null;
    public $selectedGroups = [];
    public $selectedClasses = [];
    public $selectedMonths = [];
    public $selectedStatuses = [];
    public $selectedPaymentMethods = [];
    public $dateFrom = null;
    public $dateTo = null;

    public function mount(): void
    {
        $this->selectedAcademicYear = 'all'; // Default to all years
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContributionReportMetricsWidget::class,
            ContributionReportChartsWidget::class,
        ];
    }

    public function getReportData(): array
    {
        $query = Contribution::with(['member.memberGroup', 'member.schoolClass', 'academicYear', 'recordedBy']);

        // Apply filters
        if ($this->selectedAcademicYear && $this->selectedAcademicYear !== 'all') {
            $query->where('academic_year_id', $this->selectedAcademicYear);
        }

        if (!empty($this->selectedGroups)) {
            $query->whereHas('member.memberGroup', function ($q) {
                $q->whereIn('member_groups.id', $this->selectedGroups);
            });
        }

        if (!empty($this->selectedClasses)) {
            $query->whereHas('member.schoolClass', function ($q) {
                $q->whereIn('school_classes.id', $this->selectedClasses);
            });
        }

        if (!empty($this->selectedMonths)) {
            $query->whereIn('month_name', $this->selectedMonths);
        }

        if (!empty($this->selectedStatuses)) {
            $query->whereHas('member', function ($q) {
                $q->whereIn('members.status', $this->selectedStatuses);
            });
        }

        if (!empty($this->selectedPaymentMethods)) {
            $query->whereIn('payment_method', $this->selectedPaymentMethods);
        }

        if ($this->dateFrom) {
            $query->whereDate('payment_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('payment_date', '<=', $this->dateTo);
        }

        $contributions = $query->orderBy('payment_date', 'desc')->get();

        // Calculate metrics
        $totalExpected = 0;
        $totalCollected = 0;
        $totalOutstanding = 0;

        if ($this->selectedAcademicYear && $this->selectedAcademicYear !== 'all') {
            // Calculate expected amounts for selected academic year
            $members = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->whereHas('memberGroup')
                ->when(!empty($this->selectedGroups), function ($q) {
                    $q->whereIn('member_group_id', $this->selectedGroups);
                })
                ->when(!empty($this->selectedClasses), function ($q) {
                    $q->whereIn('school_class_id', $this->selectedClasses);
                })
                ->get();

            foreach ($members as $member) {
                $months = !empty($this->selectedMonths) ? $this->selectedMonths : EthiopianDateHelper::getMonthsForContribution();
                
                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                        ->forMonth($monthName)
                        ->active()
                        ->value('amount') ?? 0;
                    
                    $totalExpected += $expectedAmount;
                }
            }
        }

        $totalCollected = $contributions->sum('amount');
        $totalOutstanding = $totalExpected - $totalCollected;
        $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

        // Get top 5 contributors
        $topContributors = $contributions
            ->groupBy('member_id')
            ->map(function ($group) {
                return [
                    'member' => $group->first()->member,
                    'total' => $group->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->take(5)
            ->values();

        return [
            'contributions' => $contributions,
            'totalExpected' => $totalExpected,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'collectionRate' => round($collectionRate, 2),
            'topContributors' => $topContributors,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'reportData' => $this->getReportData(),
            'academicYears' => AcademicYear::orderBy('name')->pluck('name', 'id')->all(),
            'groups' => MemberGroup::active()->pluck('name', 'id')->all(),
            'classes' => SchoolClass::orderBy('name')->pluck('name', 'id')->all(),
            'months' => EthiopianDateHelper::getMonthsForContribution(),
            'paymentMethods' => ['Cash', 'Check', 'Mobile Money', 'Bank Transfer', 'Other'],
            'memberStatuses' => ['Active', 'Member'],
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Report Filters')
                ->schema([
                    Forms\Components\Select::make('selectedAcademicYear')
                        ->label('Academic Year')
                        ->options(function () {
                            $years = AcademicYear::orderBy('name')->pluck('name', 'id')->all();
                            return ['all' => 'All Years'] + $years;
                        })
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\CheckboxList::make('selectedGroups')
                        ->label('Member Groups')
                        ->options(function () {
                            return MemberGroup::active()->pluck('name', 'id')->all();
                        })
                        ->columns(3)
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\CheckboxList::make('selectedClasses')
                        ->label('Classes')
                        ->options(function () {
                            return SchoolClass::orderBy('name')->pluck('name', 'id')->all();
                        })
                        ->columns(3)
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\CheckboxList::make('selectedMonths')
                        ->label('Months')
                        ->options(EthiopianDateHelper::getMonthsForContribution())
                        ->columns(4)
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\CheckboxList::make('selectedStatuses')
                        ->label('Member Status')
                        ->options(['Active' => 'Active', 'Member' => 'Member'])
                        ->columns(2)
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Forms\Components\CheckboxList::make('selectedPaymentMethods')
                        ->label('Payment Methods')
                        ->options(['Cash' => 'Cash', 'Check' => 'Check', 'Mobile Money' => 'Mobile Money', 'Bank Transfer' => 'Bank Transfer', 'Other' => 'Other'])
                        ->columns(3)
                        ->afterStateUpdated(fn () => $this->resetPage()),

                    Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('dateFrom')
                                ->label('Date From')
                                ->afterStateUpdated(fn () => $this->resetPage()),
                            
                            Forms\Components\DatePicker::make('dateTo')
                                ->label('Date To')
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
        $this->validate([
            'selectedAcademicYear' => 'nullable|string',
            'selectedGroups' => 'array',
            'selectedClasses' => 'array',
            'selectedMonths' => 'array',
            'selectedStatuses' => 'array',
            'selectedPaymentMethods' => 'array',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date|after_or_equal:dateFrom',
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset([
            'selectedAcademicYear', 'selectedGroups', 'selectedClasses',
            'selectedMonths', 'selectedStatuses', 'selectedPaymentMethods',
            'dateFrom', 'dateTo'
        ]);
        $this->selectedAcademicYear = 'all';
    }
}

