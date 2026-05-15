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
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FinancialStatementPage extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-duplicate';
    }

    public static function getNavigationLabel(): string
    {
        return 'Financial Statement';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    protected string $view = 'filament.pages.financial-statement';

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->can('page.financial.statement');
    }

    public string $periodType = 'monthly';
    public int $selectedYear;
    public int $selectedMonth;
    public int $selectedQuarter;

    public function mount(): void
    {
        $activeYear = AcademicYear::where('status', 'Active')->first();

        $this->selectedYear    = $activeYear?->start_date?->year ?? now()->year;
        $this->selectedMonth   = now()->month;
        $this->selectedQuarter = (int) ceil(now()->month / 3);
    }

    // -------------------------------------------------------------------------
    // Form actions
    // -------------------------------------------------------------------------

    public function resetForm(): void
    {
        $activeYear = AcademicYear::where('status', 'Active')->first();

        $this->periodType      = 'monthly';
        $this->selectedYear    = $activeYear?->start_date?->year ?? now()->year;
        $this->selectedMonth   = now()->month;
        $this->selectedQuarter = (int) ceil(now()->month / 3);
        $this->resetErrorBag();
    }

    public function generateStatement(): void
    {
        $this->validate([
            'periodType'      => 'required|in:monthly,quarterly,annual',
            'selectedYear'    => 'required|integer|min:2020|max:' . (now()->year + 1),
            'selectedMonth'   => 'required_if:periodType,monthly|integer|min:1|max:12',
            'selectedQuarter' => 'required_if:periodType,quarterly|integer|min:1|max:4',
        ]);

        try {
            $statementData = $this->generateStatementData();
            $pdf           = $this->generatePDF($statementData);

            Log::channel('audit')->warning('Tier 2 Audit Log', [
                'tier'         => 2,
                'action'       => 'financial_statement_generated',
                'period_type'  => $this->periodType,
                'period'       => $this->getPeriodDescription(),
                'generated_by' => Auth::id(),
                'record_count' => count($statementData['contributions']) + count($statementData['donations']),
                'timestamp'    => now()->toDateTimeString(),
            ]);

            $filename = 'financial-statement-' . $this->getPeriodDescription() . '.pdf';

            session()->flash('message', 'Financial statement generated successfully.');

            $this->dispatch('download-pdf', [
                'content'  => base64_encode($pdf->output()),
                'filename' => $filename,
            ]);

        } catch (\Exception $e) {
            Log::error('Financial statement generation failed', [
                'error'       => $e->getMessage(),
                'period_type' => $this->periodType,
                'period'      => $this->getPeriodDescription(),
            ]);

            $this->addError('generation_error', 'Failed to generate statement: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Data building
    // -------------------------------------------------------------------------

    protected function generateStatementData(): array
    {
        $startDate = $this->getStartDate();
        $endDate   = $this->getEndDate();

        $contributions = Contribution::with(['member.currentGroupAssignment.group', 'academicYear', 'recordedBy'])
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->orderBy('payment_date')
            ->get();

        $donations = Donation::with(['recordedBy'])
            ->whereDate('donation_date', '>=', $startDate)
            ->whereDate('donation_date', '<=', $endDate)
            ->orderBy('donation_date')
            ->get();

        $contributionsByGroup = $contributions->groupBy(function ($contribution) {
            return $contribution->member->currentGroupAssignment?->group_id;
        });

        // --- Fix: use status column; fall back to latest if none active ----------
        $activeYear = AcademicYear::where('status', 'active')->first()
            ?? AcademicYear::latest('start_date')->first();

        $outstandingContributions = [];

        if ($activeYear) {
            $members = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->whereHas('currentGroupAssignment')
                ->with(['currentGroupAssignment.group'])
                ->get();

            foreach ($members as $member) {
                $months = EthiopianDateHelper::getContributionMonths();

                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $member->currentGroupAssignment?->group_id)
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
                                'member'      => $member,
                                'month'       => $monthName,
                                'expected'    => $expectedAmount,
                                'paid'        => $paidAmount,
                                'outstanding' => $expectedAmount - $paidAmount,
                            ];
                        }
                    }
                }
            }
        }

        $totalContributions = $contributions->sum('amount');
        $totalDonations     = $donations->sum('amount');
        $totalOutstanding   = collect($outstandingContributions)->sum('outstanding');
        $grandTotal         = $totalContributions + $totalDonations;

        $groupSummary = [];
        foreach ($contributionsByGroup as $groupContributions) {
            $groupName      = $groupContributions->first()->member->memberGroup?->name ?? 'Unknown';
            $groupSummary[] = [
                'group_name'         => $groupName,
                'total_amount'       => $groupContributions->sum('amount'),
                'contribution_count' => $groupContributions->count(),
                'average_amount'     => $groupContributions->count() > 0
                    ? $groupContributions->sum('amount') / $groupContributions->count()
                    : 0,
            ];
        }

        $periodSummary = [];
        if ($this->periodType === 'monthly') {
            $periodSummary[] = [
                'period'             => EthiopianDateHelper::getEthiopianMonthName($this->selectedMonth) . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear),
                'contributions'      => $totalContributions,
                'donations'          => $totalDonations,
                'total'              => $grandTotal,
                'contribution_count' => $contributions->count(),
                'donation_count'     => $donations->count(),
            ];
        } elseif ($this->periodType === 'quarterly') {
            foreach ($this->getQuarterMonths($this->selectedQuarter) as $month) {
                $mc              = $contributions->where('month_name', EthiopianDateHelper::getEthiopianMonthName($month));
                $md              = $donations->filter(fn ($d) => (int) date('m', strtotime($d->donation_date)) === $month);
                $periodSummary[] = [
                    'period'             => EthiopianDateHelper::getEthiopianMonthName($month) . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear),
                    'contributions'      => $mc->sum('amount'),
                    'donations'          => $md->sum('amount'),
                    'total'              => $mc->sum('amount') + $md->sum('amount'),
                    'contribution_count' => $mc->count(),
                    'donation_count'     => $md->count(),
                ];
            }
        }

        return [
            'period_type'              => $this->periodType,
            'period_description'       => $this->getPeriodDescription(),
            'ethiopian_period'         => $this->getEthiopianPeriodDescription(),
            'start_date'               => $startDate,
            'end_date'                 => $endDate,
            'generated_at'             => now(),
            'generated_by'             => Auth::user()->name,
            'church_info'              => $this->getChurchInfo(),
            'contributions'            => $contributions,
            'donations'                => $donations,
            'contributions_by_group'   => $groupSummary,
            'contributions_by_month'   => $periodSummary,
            'outstanding_contributions' => $outstandingContributions,
            'summary'                  => [
                'total_contributions' => $totalContributions,
                'total_donations'     => $totalDonations,
                'total_outstanding'   => $totalOutstanding,
                'grand_total'         => $grandTotal,
                'contribution_count'  => $contributions->count(),
                'donation_count'      => $donations->count(),
                'unique_contributors' => $contributions->groupBy('member_id')->count(),
                'unique_donors'       => $donations->groupBy('donor_name')->count(),
            ],
        ];
    }

    protected function getChurchInfo(): array
    {
        return [
            'name_en'     => SiteSetting::get('church_name_en', 'FINOTE TSIDIK'),
            'name_am'     => SiteSetting::get('church_name_am', 'Finote Tsidik'),
            'address'     => SiteSetting::get('church_address', ''),
            'phone'       => SiteSetting::get('church_phone', ''),
            'email'       => SiteSetting::get('church_email', ''),
            'footer_text' => SiteSetting::get('church_footer_text', ''),
            'logo'        => SiteSetting::get('logo'),
        ];
    }

    protected function getEthiopianPeriodDescription(): string
    {
        if ($this->periodType === 'monthly') {
            return EthiopianDateHelper::getEthiopianMonthName($this->selectedMonth) . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear);
        } elseif ($this->periodType === 'quarterly') {
            return 'Q' . $this->selectedQuarter . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear);
        }

        return EthiopianDateHelper::getEthiopianYear($this->selectedYear);
    }

    protected function getQuarterMonths(int $quarter): array
    {
        return [1 => [1,2,3], 2 => [4,5,6], 3 => [7,8,9], 4 => [10,11,12]][$quarter] ?? [1,2,3];
    }

    protected function getStartDate(): string
    {
        if ($this->periodType === 'monthly') {
            return sprintf('%04d-%02d-01', $this->selectedYear, $this->selectedMonth);
        } elseif ($this->periodType === 'quarterly') {
            $first = min($this->getQuarterMonths($this->selectedQuarter));
            return sprintf('%04d-%02d-01', $this->selectedYear, $first);
        }

        return "{$this->selectedYear}-01-01";
    }

    protected function getEndDate(): string
    {
        if ($this->periodType === 'monthly') {
            $days = cal_days_in_month(CAL_GREGORIAN, $this->selectedMonth, $this->selectedYear);
            return sprintf('%04d-%02d-%02d', $this->selectedYear, $this->selectedMonth, $days);
        } elseif ($this->periodType === 'quarterly') {
            $last = max($this->getQuarterMonths($this->selectedQuarter));
            $days = cal_days_in_month(CAL_GREGORIAN, $last, $this->selectedYear);
            return sprintf('%04d-%02d-%02d', $this->selectedYear, $last, $days);
        }

        return "{$this->selectedYear}-12-31";
    }

    protected function getPeriodDescription(): string
    {
        if ($this->periodType === 'monthly') {
            return date('F', mktime(0, 0, 0, $this->selectedMonth, 1)) . ' ' . $this->selectedYear;
        } elseif ($this->periodType === 'quarterly') {
            return "Q{$this->selectedQuarter} {$this->selectedYear}";
        }

        return "Year {$this->selectedYear}";
    }

    // -------------------------------------------------------------------------
    // PDF — KEY FIX: wrap in ['data' => $data] so $data is defined in the blade
    // -------------------------------------------------------------------------

    protected function generatePDF(array $data)
    {
        $pdf = Pdf::loadView('pdf.financial-statement', ['data' => $data]);
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
                ->color('primary'),
        ];
    }
}
