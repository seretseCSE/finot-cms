<?php

namespace App\Filament\Pages;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Donation;
use App\Models\Member;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialStatementPage extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-document-duplicate'; }

    public static function getNavigationLabel(): string { return 'Financial Statement'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    protected string $view = 'filament.pages.financial-statement';

    public static function getNavigationSort(): ?int { return 6; }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public $periodType = 'monthly';
    public $selectedYear = null;
    public $selectedMonth = null;
    public $selectedQuarter = null;

    public function mount(): void
    {
        $this->selectedYear = now()->year;
        $this->selectedMonth = now()->month;

        // Determine current quarter
        $this->selectedQuarter = ceil(now()->month / 3);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Statement Period')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Forms\Components\Select::make('periodType')
                                ->label('Period Type')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'quarterly' => 'Quarterly',
                                    'annual' => 'Annual',
                                ])
                                ->default('monthly')
                                ->reactive()
                                ->afterStateUpdated(fn ($state) => $this->updatePeriodFields($state)),

                            Forms\Components\Select::make('selectedYear')
                                ->label('Year')
                                ->options(function () {
                                    $years = [];
                                    for ($year = now()->year - 5; $year <= now()->year + 1; $year++) {
                                        $years[$year] = $year;
                                    }
                                    return $years;
                                })
                                ->default(now()->year)
                                ->reactive(),

                            Forms\Components\Placeholder::make('periodSelector')
                                ->label('Period')
                                ->content(function ($get) {
                                    $periodType = $get('periodType');

                                    if ($periodType === 'monthly') {
                                        return Forms\Components\Select::make('selectedMonth')
                                            ->label('Month')
                                            ->options([
                                                1 => 'January', 2 => 'February', 3 => 'March',
                                                4 => 'April', 5 => 'May', 6 => 'June',
                                                7 => 'July', 8 => 'August', 9 => 'September',
                                                10 => 'October', 11 => 'November', 12 => 'December',
                                            ])
                                            ->default(now()->month);
                                    } elseif ($periodType === 'quarterly') {
                                        return Forms\Components\Select::make('selectedQuarter')
                                            ->label('Quarter')
                                            ->options([
                                                1 => 'Q1 (Jan-Mar)', 2 => 'Q2 (Apr-Jun)',
                                                3 => 'Q3 (Jul-Sep)', 4 => 'Q4 (Oct-Dec)',
                                            ])
                                            ->default(ceil(now()->month / 3));
                                    }

                                    return null;
                                }),
                        ]),
                ]),
        ];
    }

    public function updatePeriodFields($periodType): void
    {
        // This will trigger Livewire reactivity
        // The actual field updates are handled in the placeholder content
    }

    public function generateStatement(): void
    {
        $this->validate([
            'periodType' => 'required|in:monthly,quarterly,annual',
            'selectedYear' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'selectedMonth' => 'required_if:periodType,monthly|integer|min:1|max:12',
            'selectedQuarter' => 'required_if:periodType,quarterly|integer|min:1|max:4',
        ]);

        try {
            $statementData = $this->generateStatementData();
            $pdf = $this->generatePDF($statementData);

            // Log to Tier-2 audit trail
            Log::channel('audit')->warning('Tier 2 Audit Log', [
                'tier' => 2,
                'action' => 'financial_statement_generated',
                'period_type' => $this->periodType,
                'period' => $this->getPeriodDescription(),
                'generated_by' => Auth::id(),
                'record_count' => count($statementData['contributions']) + count($statementData['donations']),
                'timestamp' => now()->toDateTimeString(),
            ]);

            // Download the PDF
            response()->streamDownload(
                $pdf->output(),
                'financial-statement-' . $this->getPeriodDescription() . '.pdf'
            )->send();

        } catch (\Exception $e) {
            Log::error('Financial statement generation failed', [
                'error' => $e->getMessage(),
                'period_type' => $this->periodType,
                'period' => $this->getPeriodDescription(),
            ]);

            $this->addError('generation_error', 'Failed to generate statement: ' . $e->getMessage());
        }
    }

    protected function generateStatementData(): array
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        // Get contributions for the period
        $contributions = Contribution::with(['member.memberGroup', 'academicYear', 'recordedBy'])
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->orderBy('payment_date')
            ->get();

        // Get donations for the period
        $donations = Donation::with(['recordedBy'])
            ->whereDate('donation_date', '>=', $startDate)
            ->whereDate('donation_date', '<=', $endDate)
            ->orderBy('donation_date')
            ->get();

        // Calculate contributions by group and month
        $contributionsByGroup = $contributions->groupBy('member.member_group_id');
        $contributionsByMonth = $contributions->groupBy('month_name');

        // Calculate outstanding contributions (current academic year only)
        $activeYear = AcademicYear::where('is_active', true)->first();
        $outstandingContributions = [];

        if ($activeYear) {
            $members = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->whereHas('memberGroup')
                ->with(['memberGroup'])
                ->get();

            foreach ($members as $member) {
                $months = EthiopianDateHelper::getMonthsForContribution();

                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                        ->forMonth($monthName)
                        ->active()
                        ->value('amount') ?? 0;

                    if ($expectedAmount > 0) {
                        $paidAmount = $contributions
                            ->where('member_id', $member->id)
                            ->where('month_name', $monthName)
                            ->sum('amount');

                        if ($paidAmount < $expectedAmount) {
                            $outstandingContributions[] = [
                                'member' => $member,
                                'month' => $monthName,
                                'expected' => $expectedAmount,
                                'paid' => $paidAmount,
                                'outstanding' => $expectedAmount - $paidAmount,
                            ];
                        }
                    }
                }
            }
        }

        // Calculate summary statistics
        $totalContributions = $contributions->sum('amount');
        $totalDonations = $donations->sum('amount');
        $totalOutstanding = collect($outstandingContributions)->sum('outstanding');
        $grandTotal = $totalContributions + $totalDonations;

        // Group performance summary
        $groupSummary = [];
        foreach ($contributionsByGroup as $groupId => $groupContributions) {
            $groupName = $groupContributions->first()->member->memberGroup->name ?? 'Unknown';
            $groupSummary[] = [
                'group_name' => $groupName,
                'total_amount' => $groupContributions->sum('amount'),
                'contribution_count' => $groupContributions->count(),
                'average_amount' => $groupContributions->count() > 0 ? $groupContributions->sum('amount') / $groupContributions->count() : 0,
            ];
        }

        // Monthly/Quarterly summary
        $periodSummary = [];
        if ($this->periodType === 'monthly') {
            $periodSummary[] = [
                'period' => EthiopianDateHelper::getEthiopianMonthName($this->selectedMonth) . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear),
                'contributions' => $totalContributions,
                'donations' => $totalDonations,
                'total' => $grandTotal,
                'contribution_count' => $contributions->count(),
                'donation_count' => $donations->count(),
            ];
        } elseif ($this->periodType === 'quarterly') {
            $quarterMonths = $this->getQuarterMonths($this->selectedQuarter);
            foreach ($quarterMonths as $month) {
                $monthContributions = $contributions->where('month_name', EthiopianDateHelper::getEthiopianMonthName($month));
                $monthDonations = $donations->whereMonth('donation_date', $month);
                
                $periodSummary[] = [
                    'period' => EthiopianDateHelper::getEthiopianMonthName($month) . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear),
                    'contributions' => $monthContributions->sum('amount'),
                    'donations' => $monthDonations->sum('amount'),
                    'total' => $monthContributions->sum('amount') + $monthDonations->sum('amount'),
                    'contribution_count' => $monthContributions->count(),
                    'donation_count' => $monthDonations->count(),
                ];
            }
        }

        return [
            'period_type' => $this->periodType,
            'period_description' => $this->getPeriodDescription(),
            'ethiopian_period' => $this->getEthiopianPeriodDescription(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_at' => now(),
            'generated_by' => Auth::user()->name,
            'church_info' => $this->getChurchInfo(),
            'contributions' => $contributions,
            'donations' => $donations,
            'contributions_by_group' => $groupSummary,
            'contributions_by_month' => $periodSummary,
            'outstanding_contributions' => $outstandingContributions,
            'summary' => [
                'total_contributions' => $totalContributions,
                'total_donations' => $totalDonations,
                'total_outstanding' => $totalOutstanding,
                'grand_total' => $grandTotal,
                'contribution_count' => $contributions->count(),
                'donation_count' => $donations->count(),
                'unique_contributors' => $contributions->groupBy('member_id')->count(),
                'unique_donors' => $donations->groupBy('donor_name')->count(),
            ],
        ];
    }

    /**
     * Get church information for the statement
     */
    protected function getChurchInfo(): array
    {
        return [
            'name_en' => SiteSetting::get('church_name_en', 'FINOTE TSIDIK'),
            'name_am' => SiteSetting::get('church_name_am', 'ፊኖተ ጽዲክ'),
            'address' => SiteSetting::get('church_address', ''),
            'phone' => SiteSetting::get('church_phone', ''),
            'email' => SiteSetting::get('church_email', ''),
            'logo' => SiteSetting::get('logo'),
        ];
    }

    /**
     * Get Ethiopian date period description
     */
    protected function getEthiopianPeriodDescription(): string
    {
        if ($this->periodType === 'monthly') {
            return EthiopianDateHelper::getEthiopianMonthName($this->selectedMonth) . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear);
        } elseif ($this->periodType === 'quarterly') {
            $ethiopianYear = EthiopianDateHelper::getEthiopianYear($this->selectedYear);
            return "Q{$this->selectedQuarter} {$ethiopianYear}";
        } else {
            return EthiopianDateHelper::getEthiopianYear($this->selectedYear);
        }
    }

    /**
     * Get months for a given quarter
     */
    protected function getQuarterMonths(int $quarter): array
    {
        $quarters = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12],
        ];
        
        return $quarters[$quarter] ?? [1, 2, 3];
    }

    protected function getStartDate(): string
    {
        if ($this->periodType === 'monthly') {
            return "{$this->selectedYear}-{$this->selectedMonth}-01";
        } elseif ($this->periodType === 'quarterly') {
            $quarterMonths = $this->getQuarterMonths($this->selectedQuarter);
            $firstMonth = min($quarterMonths);
            return "{$this->selectedYear}-{$firstMonth}-01";
        } else {
            return "{$this->selectedYear}-01-01";
        }
    }

    protected function getEndDate(): string
    {
        if ($this->periodType === 'monthly') {
            return "{$this->selectedYear}-{$this->selectedMonth}-31";
        } elseif ($this->periodType === 'quarterly') {
            $quarterMonths = $this->getQuarterMonths($this->selectedQuarter);
            $lastMonth = max($quarterMonths);
            $daysInMonth = now()->setYear($this->selectedYear)->setMonth($lastMonth)->daysInMonth;
            return "{$this->selectedYear}-{$lastMonth}-{$daysInMonth}";
        } else {
            return "{$this->selectedYear}-12-31";
        }
    }

    protected function getPeriodDescription(): string
    {
        if ($this->periodType === 'monthly') {
            $monthName = date('F', mktime(0, 0, 0, $this->selectedMonth, 1));
            return "{$monthName} {$this->selectedYear}";
        } elseif ($this->periodType === 'quarterly') {
            return "Q{$this->selectedQuarter} {$this->selectedYear}";
        } else {
            return "Year {$this->selectedYear}";
        }
    }

    protected function generatePDF(array $data)
    {
        $pdf = Pdf::loadView('pdf.financial-statement', $data);
        
        // Set paper size to A4
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Statement')
                ->icon('heroicon-o-document-arrow-down')
                ->action('generateStatement')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generate Financial Statement')
                ->modalDescription('This will generate a PDF financial statement for the selected period.'),
        ];
    }
}

