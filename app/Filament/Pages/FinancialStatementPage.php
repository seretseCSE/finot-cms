<?php

namespace App\Filament\Pages;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Donation;
use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
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
            Forms\Components\Section::make('Statement Period')
                ->schema([
                    Forms\Components\Grid::make(3)
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

                    $paidAmount = Contribution::forMemberAndYear($member->id, $activeYear->id)
                        ->forMonth($monthName)
                        ->notArchived()
                        ->sum('amount') ?? 0;

                    $outstanding = $expectedAmount - $paidAmount;

                    if ($outstanding > 0) {
                        $outstandingContributions[] = [
                            'member' => $member->full_name,
                            'group' => $member->memberGroup->name,
                            'month' => $monthName,
                            'expected' => $expectedAmount,
                            'outstanding' => $outstanding,
                        ];
                    }
                }
            }
        }

        // Calculate collection trends
        $monthlyTrends = $contributions
            ->groupBy(function ($item) {
                return $item->payment_date->format('Y-m');
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->payment_date->format('F Y'),
                    'amount' => $group->sum('amount'),
                ];
            })
            ->sortBy('month')
            ->values();

        return [
            'period' => $this->getPeriodDescription(),
            'generatedDate' => now()->toDateTimeString(),
            'generatedBy' => Auth::user()->name . ' (' . Auth::user()->getRoleNames()->first() . ')',
            'ethiopianDate' => EthiopianDateHelper::toEthiopian(now()),
            'contributions' => $contributions,
            'donations' => $donations,
            'contributionsByGroup' => $contributionsByGroup,
            'contributionsByMonth' => $contributionsByMonth,
            'outstandingContributions' => $outstandingContributions,
            'monthlyTrends' => $monthlyTrends,
        ];
    }

    protected function getStartDate(): string
    {
        $year = $this->selectedYear;

        if ($this->periodType === 'monthly') {
            return "{$year}-{$this->selectedMonth}-01";
        } elseif ($this->periodType === 'quarterly') {
            $quarterStartMonth = (($this->selectedQuarter - 1) * 3) + 1;
            return "{$year}-{$quarterStartMonth}-01";
        } else {
            return "{$year}-01-01";
        }
    }

    protected function getEndDate(): string
    {
        $year = $this->selectedYear;

        if ($this->periodType === 'monthly') {
            return date('Y-m-t', mktime(0, 0, 0, $this->selectedMonth, 1, $year));
        } elseif ($this->periodType === 'quarterly') {
            $quarterEndMonth = $this->selectedQuarter * 3;
            return date('Y-m-t', mktime(0, 0, 0, $quarterEndMonth, 1, $year));
        } else {
            return "{$year}-12-31";
        }
    }

    protected function getPeriodDescription(): string
    {
        if ($this->periodType === 'monthly') {
            $monthName = date('F', mktime(0, 0, 0, $this->selectedMonth, 1, $this->selectedYear));
            return "{$monthName} {$this->selectedYear}";
        } elseif ($this->periodType === 'quarterly') {
            return "Q{$this->selectedQuarter} {$this->selectedYear}";
        } else {
            return "Annual {$this->selectedYear}";
        }
    }

    protected function generatePDF(array $data): Pdf
    {
        $pdf = Pdf::loadView('pdf.financial-statement', $data);

        // Configure PDF
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('margin-top', 20);
        $pdf->setOption('margin-bottom', 20);
        $pdf->setOption('margin-left', 15);
        $pdf->setOption('margin-right', 15);

        return $pdf;
    }

    protected function getActions(): array
    {
        return [
            Action::make('generateStatement')
                ->label('Generate Statement PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action('generateStatement')
                ->color('primary'),
        ];
    }
}

