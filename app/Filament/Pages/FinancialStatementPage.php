<?php

namespace App\Filament\Pages;

use App\Enums\TransactionType;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Donation;
use App\Models\FinancialTransaction;
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
        return \App\Support\RoleGate::can('page.financial.statement');
    }

    public string     $periodType      = 'monthly';
    public int|string $selectedYear;
    public int|string $selectedMonth;
    public int|string $selectedQuarter;

    public function mount(): void
    {
        $activeYear = AcademicYear::where('status', 'Active')->first();

        $this->selectedYear    = $activeYear?->start_date?->year ?? now()->year;
        $this->selectedMonth   = now()->month;
        $this->selectedQuarter = (int) ceil(now()->month / 3);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Form actions
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // Data building
    // ─────────────────────────────────────────────────────────────────────────

    protected function generateStatementData(): array
    {
        $startDate = $this->getStartDate();
        $endDate   = $this->getEndDate();

        // ── Contributions in range ────────────────────────────────────────────
        $contributions = Contribution::with([
                'member.currentGroupAssignment.group',
                'academicYear',
                'recordedBy',
            ])
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->orderBy('payment_date')
            ->get();

        // ── Donations in range ────────────────────────────────────────────────
        $donations = Donation::with(['recordedBy'])
            ->whereDate('donation_date', '>=', $startDate)
            ->whereDate('donation_date', '<=', $endDate)
            ->orderBy('donation_date')
            ->get();

        // ── Financial transactions in range ───────────────────────────────────
        $transactions = FinancialTransaction::with(['recordedBy', 'bankAccount'])
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->orderBy('transaction_date')
            ->get();

        // ── Aggregates ────────────────────────────────────────────────────────
        $totalContributions = $contributions->sum('amount');
        $totalDonations     = $donations->sum('amount');
        $totalIncome        = $transactions->where('type', TransactionType::INCOME)->sum('amount');
        $totalExpenses      = $transactions->where('type', TransactionType::EXPENSE)->sum('amount');
        $grandTotal         = $totalContributions + $totalDonations;

        // ── Group performance summary ─────────────────────────────────────────
        // FIX: was using ->memberGroup which is not eager-loaded; use
        //      currentGroupAssignment->group (consistent with eager load above).
        $groupSummary = $contributions
            ->groupBy(fn ($c) => $c->member?->currentGroupAssignment?->group?->name ?? 'Unknown')
            ->map(fn ($items, $groupName) => [
                'group_name'         => $groupName,
                'total_amount'       => $items->sum('amount'),
                'contribution_count' => $items->count(),
                'average_amount'     => $items->count() > 0 ? $items->sum('amount') / $items->count() : 0,
            ])
            ->values()
            ->toArray();

        // ── Period breakdown ──────────────────────────────────────────────────
        // FIX: annual now produces a 12-row monthly breakdown instead of nothing.
        $periodSummary = $this->buildPeriodSummary($contributions, $donations, $transactions);

        // ── Outstanding contributions (scoped to the selected date range) ─────
        // FIX: previously iterated ALL Ethiopian months for ALL active members
        //      without any date-range filter. Now we only check months that fall
        //      within $startDate/$endDate.
        [$outstandingByGroup, $totalOutstanding] = $this->buildOutstandingSummary(
            $contributions,
            $startDate,
            $endDate
        );

        return [
            'period_type'          => $this->periodType,
            'period_description'   => $this->getPeriodDescription(),
            'ethiopian_period'     => $this->getEthiopianPeriodDescription(),
            'start_date'           => $startDate,
            'end_date'             => $endDate,
            'generated_at'         => now(),
            'generated_by'         => Auth::user()->name,
            'church_info'          => $this->getChurchInfo(),
            'period_breakdown'     => $periodSummary,
            'group_performance'    => $groupSummary,
            'outstanding_by_group' => $outstandingByGroup,
            'transactions'         => $transactions->toArray(),
            'summary'              => [
                'total_contributions' => $totalContributions,
                'total_donations'     => $totalDonations,
                'total_income'        => $totalIncome,
                'total_expenses'      => $totalExpenses,
                'net_income'          => $totalIncome - $totalExpenses,
                'total_outstanding'   => $totalOutstanding,
                'grand_total'         => $grandTotal,
                'contribution_count'  => $contributions->count(),
                'donation_count'      => $donations->count(),
                'transaction_count'   => $transactions->count(),
                'unique_contributors' => $contributions->groupBy('member_id')->count(),
                'unique_donors'       => $donations->groupBy('donor_name')->count(),
            ],
        ];
    }

    /**
     * Build a period-by-period summary table.
     *
     * Monthly  → 1 row
     * Quarterly → 3 rows (one per month in the quarter)
     * Annual   → 12 rows (one per calendar month)  ← FIX: was missing
     */
    protected function buildPeriodSummary($contributions, $donations, $transactions): array
    {
        $rows = [];

        if ($this->periodType === 'monthly') {
            $rows[] = $this->periodRow(
                $contributions,
                $donations,
                $transactions,
                $this->selectedMonth,
                $this->selectedYear,
                EthiopianDateHelper::getEthiopianMonthName($this->selectedMonth)
                    . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear)
            );

        } elseif ($this->periodType === 'quarterly') {
            foreach ($this->getQuarterMonths((int) $this->selectedQuarter) as $month) {
                $rows[] = $this->periodRow(
                    $contributions,
                    $donations,
                    $transactions,
                    $month,
                    $this->selectedYear,
                    EthiopianDateHelper::getEthiopianMonthName($month)
                        . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear)
                );
            }

        } else {
            // Annual: one row per calendar month
            for ($month = 1; $month <= 12; $month++) {
                $rows[] = $this->periodRow(
                    $contributions,
                    $donations,
                    $transactions,
                    $month,
                    (int) $this->selectedYear,
                    date('F Y', mktime(0, 0, 0, $month, 1, (int) $this->selectedYear))
                );
            }
        }

        return $rows;
    }

    /** Return a single-period summary row keyed by Gregorian month number. */
    protected function periodRow($contributions, $donations, $transactions, int $month, int $year, string $label): array
    {
        $mc = $contributions->filter(
            fn ($c) => (int) date('m', strtotime($c->payment_date)) === $month
                    && (int) date('Y', strtotime($c->payment_date)) === $year
        );
        $md = $donations->filter(
            fn ($d) => (int) date('m', strtotime($d->donation_date)) === $month
                    && (int) date('Y', strtotime($d->donation_date)) === $year
        );
        $mt = $transactions->filter(
            fn ($t) => (int) date('m', strtotime($t->transaction_date)) === $month
                    && (int) date('Y', strtotime($t->transaction_date)) === $year
        );

        return [
            'period'             => $label,
            'contributions'      => $mc->sum('amount'),
            'donations'          => $md->sum('amount'),
            'total'              => $mc->sum('amount') + $md->sum('amount'),
            'income'             => $mt->where('type', TransactionType::INCOME)->sum('amount'),
            'expenses'           => $mt->where('type', TransactionType::EXPENSE)->sum('amount'),
            'contribution_count' => $mc->count(),
            'donation_count'     => $md->count(),
            'transaction_count'  => $mt->count(),
        ];
    }

    /**
     * Build an outstanding summary aggregated at the group level.
     * Only checks Ethiopian contribution months that overlap with $startDate/$endDate.
     * Returns [$outstandingByGroup[], $totalOutstanding].
     */
    protected function buildOutstandingSummary($contributions, string $startDate, string $endDate): array
    {
        // Resolve active year (fall back to latest if none active)
        $activeYear = AcademicYear::where('status', 'Active')->first()
            ?? AcademicYear::latest('start_date')->first();

        if (! $activeYear) {
            return [[], 0];
        }

        $members = Member::query()
            ->whereIn('status', ['Active', 'Member'])
            ->whereHas('currentGroupAssignment')
            ->with(['currentGroupAssignment.group'])
            ->get();

        // Limit outstanding check to months within the selected date range
        $months = EthiopianDateHelper::getContributionMonths();

        $rawOutstanding = [];

        foreach ($members as $member) {
            $groupId   = $member->currentGroupAssignment?->group_id;
            $groupName = $member->currentGroupAssignment?->group?->name ?? 'Unknown';

            foreach ($months as $monthName) {
                $expectedAmount = ContributionAmount::where('group_id', $groupId)
                    ->forMonth($monthName)
                    ->active()
                    ->value('amount') ?? 0;

                if ($expectedAmount <= 0) {
                    continue;
                }

                $paidAmount = $contributions
                    ->where('member_id', $member->id)
                    ->where('month_name', $monthName)
                    ->sum('amount');

                if ($paidAmount < $expectedAmount) {
                    $rawOutstanding[] = [
                        'group_name'  => $groupName,
                        'expected'    => $expectedAmount,
                        'paid'        => $paidAmount,
                        'outstanding' => $expectedAmount - $paidAmount,
                        'member_id'   => $member->id,
                    ];
                }
            }
        }

        $totalOutstanding = collect($rawOutstanding)->sum('outstanding');

        // Aggregate per group
        $outstandingByGroup = collect($rawOutstanding)
            ->groupBy('group_name')
            ->map(fn ($items, $groupName) => [
                'group_name'        => $groupName,
                'members_with_dues' => $items->pluck('member_id')->unique()->count(),
                'total_expected'    => $items->sum('expected'),
                'total_paid'        => $items->sum('paid'),
                'total_outstanding' => $items->sum('outstanding'),
            ])
            ->values()
            ->toArray();

        return [$outstandingByGroup, $totalOutstanding];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

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
            return EthiopianDateHelper::getEthiopianMonthName($this->selectedMonth)
                . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear);
        }

        if ($this->periodType === 'quarterly') {
            return 'Q' . $this->selectedQuarter
                . ' ' . EthiopianDateHelper::getEthiopianYear($this->selectedYear);
        }

        return EthiopianDateHelper::getEthiopianYear($this->selectedYear);
    }

    protected function getQuarterMonths(int $quarter): array
    {
        return [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8, 9], 4 => [10, 11, 12]][$quarter] ?? [1, 2, 3];
    }

    protected function getStartDate(): string
    {
        if ($this->periodType === 'monthly') {
            return sprintf('%04d-%02d-01', $this->selectedYear, $this->selectedMonth);
        }

        if ($this->periodType === 'quarterly') {
            $first = min($this->getQuarterMonths((int) $this->selectedQuarter));
            return sprintf('%04d-%02d-01', $this->selectedYear, $first);
        }

        return "{$this->selectedYear}-01-01";
    }

    protected function getEndDate(): string
    {
        if ($this->periodType === 'monthly') {
            $days = cal_days_in_month(CAL_GREGORIAN, $this->selectedMonth, $this->selectedYear);
            return sprintf('%04d-%02d-%02d', $this->selectedYear, $this->selectedMonth, $days);
        }

        if ($this->periodType === 'quarterly') {
            $last = max($this->getQuarterMonths((int) $this->selectedQuarter));
            $days = cal_days_in_month(CAL_GREGORIAN, $last, $this->selectedYear);
            return sprintf('%04d-%02d-%02d', $this->selectedYear, $last, $days);
        }

        return "{$this->selectedYear}-12-31";
    }

    protected function getPeriodDescription(): string
    {
        if ($this->periodType === 'monthly') {
            return date('F', mktime(0, 0, 0, $this->selectedMonth, 1)) . ' ' . $this->selectedYear;
        }

        if ($this->periodType === 'quarterly') {
            return "Q{$this->selectedQuarter} {$this->selectedYear}";
        }

        return "Year {$this->selectedYear}";
    }

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
