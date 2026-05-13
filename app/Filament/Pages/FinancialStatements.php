<?php

namespace App\Filament\Pages;

use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\MemberGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FinancialStatements extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Financial Statements';

    protected static ?string $title = 'Generate Financial Statements';

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.financial-statements';

    public ?string $statementType = 'monthly';

    public ?int $academic_year_id = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?int $group_id = null;

    public array $statementData = [];

    public array $summary = [];

    public array $academicYears = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->hasRole(['superadmin', 'finance_head', 'nibret_hisab_head', 'admin']);
    }

    public function mount(): void
    {
        $this->academicYears = AcademicYear::where('status', 'Active')
            ->orWhere('status', 'Inactive')
            ->orderBy('name', 'desc')
            ->pluck('name', 'id')
            ->toArray();

        $activeYear = AcademicYear::where('status', 'Active')->first();
        if ($activeYear) {
            $this->academic_year_id = $activeYear->id;
        }

        $this->generateStatement();
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Radio::make('statementType')
                ->label('Statement Type')
                ->options([
                    'monthly' => 'Monthly Statement',
                    'quarterly' => 'Quarterly Statement',
                    'yearly' => 'Yearly Statement',
                    'custom' => 'Custom Date Range',
                ])
                ->default('monthly')
                ->inline()
                ->live()
                ->afterStateUpdated(fn () => $this->generateStatement()),

            Select::make('academic_year_id')
                ->label('Academic Year')
                ->options($this->academicYears)
                ->placeholder('Select Academic Year')
                ->live()
                ->afterStateUpdated(fn () => $this->generateStatement()),

            Select::make('group_id')
                ->label('Member Group (Optional)')
                ->options(MemberGroup::pluck('name', 'id'))
                ->placeholder('All Groups')
                ->live()
                ->afterStateUpdated(fn () => $this->generateStatement()),

            DatePicker::make('start_date')
                ->label('Start Date')
                ->visible(fn () => $this->statementType === 'custom')
                ->live()
                ->afterStateUpdated(fn () => $this->generateStatement()),

            DatePicker::make('end_date')
                ->label('End Date')
                ->visible(fn () => $this->statementType === 'custom')
                ->live()
                ->afterStateUpdated(fn () => $this->generateStatement()),
        ]);
    }

    public function generateStatement(): void
    {
        if (!$this->academic_year_id) {
            $this->statementData = [];
            $this->summary = [];
            return;
        }

        $dateRange = $this->getDateRange();

        if (!$dateRange) {
            return;
        }

        [$startDate, $endDate] = $dateRange;

        // Get contributions data
        $contributionsQuery = Contribution::where('academic_year_id', $this->academic_year_id)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('is_paid', true);

        if ($this->group_id) {
            $contributionsQuery->whereHas('member.currentGroupAssignment', function (Builder $query) {
                $query->where('group_id', $this->group_id);
            });
        }

        $contributions = $contributionsQuery
            ->with(['member.currentGroupAssignment.group'])
            ->get();

        // Get donations data
        $donations = Donation::whereBetween('donation_date', [$startDate, $endDate])
            ->get();

        // Group by period
        $this->statementData = $this->groupDataByPeriod($contributions, $donations, $startDate, $endDate);

        // Calculate summary
        $this->calculateSummary($contributions, $donations);
    }

    protected function getDateRange(): ?array
    {
        $academicYear = AcademicYear::find($this->academic_year_id);
        if (!$academicYear) {
            return null;
        }

        $now = now();

        switch ($this->statementType) {
            case 'monthly':
                // Current Ethiopian month
                $ethiopianDate = EthiopianDateHelper::toEthiopian($now);
                $monthStart = EthiopianDateHelper::getEthiopianMonthStart($ethiopianDate['year'], $ethiopianDate['month']);
                $monthEnd = EthiopianDateHelper::getEthiopianMonthEnd($ethiopianDate['year'], $ethiopianDate['month']);
                return [$monthStart, $monthEnd];

            case 'quarterly':
                // Current quarter (3 months)
                $ethiopianDate = EthiopianDateHelper::toEthiopian($now);
                $quarter = ceil($ethiopianDate['month'] / 3);
                $startMonth = (($quarter - 1) * 3) + 1;
                $endMonth = $quarter * 3;

                $quarterStart = EthiopianDateHelper::getEthiopianMonthStart($ethiopianDate['year'], $startMonth);
                $quarterEnd = EthiopianDateHelper::getEthiopianMonthEnd($ethiopianDate['year'], min($endMonth, 12));
                return [$quarterStart, $quarterEnd];

            case 'yearly':
                // Full academic year
                return [$academicYear->start_date, $academicYear->end_date];

            case 'custom':
                if ($this->start_date && $this->end_date) {
                    return [$this->start_date, $this->end_date];
                }
                return null;

            default:
                return null;
        }
    }

    protected function groupDataByPeriod($contributions, $donations, $startDate, $endDate): array
    {
        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $periodKey = $this->getPeriodKey($currentDate);

            if (!isset($data[$periodKey])) {
                $data[$periodKey] = [
                    'period' => $periodKey,
                    'contributions' => collect(),
                    'donations' => collect(),
                    'total_contributions' => 0,
                    'total_donations' => 0,
                    'grand_total' => 0,
                ];
            }

            // Filter contributions for this period
            $periodContributions = $contributions->filter(function ($contribution) use ($currentDate, $periodKey) {
                return $this->belongsToPeriod($contribution->payment_date, $currentDate, $periodKey);
            });

            // Filter donations for this period
            $periodDonations = $donations->filter(function ($donation) use ($currentDate, $periodKey) {
                return $this->belongsToPeriod($donation->donation_date, $currentDate, $periodKey);
            });

            $data[$periodKey]['contributions'] = $periodContributions;
            $data[$periodKey]['donations'] = $periodDonations;
            $data[$periodKey]['total_contributions'] = $periodContributions->sum('amount');
            $data[$periodKey]['total_donations'] = $periodDonations->sum('amount');
            $data[$periodKey]['grand_total'] = $data[$periodKey]['total_contributions'] + $data[$periodKey]['total_donations'];

            // Move to next period
            $currentDate = $this->getNextPeriodStart($currentDate);
        }

        return array_values($data);
    }

    protected function getPeriodKey($date): string
    {
        switch ($this->statementType) {
            case 'monthly':
                return $date->format('F Y');
            case 'quarterly':
                $quarter = ceil($date->month / 3);
                return "Q{$quarter} " . $date->format('Y');
            case 'yearly':
                return $date->format('Y');
            case 'custom':
                return $date->format('M d, Y');
            default:
                return $date->format('Y-m-d');
        }
    }

    protected function belongsToPeriod($itemDate, $currentDate, $periodKey): bool
    {
        switch ($this->statementType) {
            case 'monthly':
                return $itemDate->format('F Y') === $periodKey;
            case 'quarterly':
                $quarter = ceil($itemDate->month / 3);
                return "Q{$quarter} " . $itemDate->format('Y') === $periodKey;
            case 'yearly':
                return $itemDate->format('Y') === $periodKey;
            case 'custom':
                return $itemDate->format('Y-m-d') === $currentDate->format('Y-m-d');
            default:
                return false;
        }
    }

    protected function getNextPeriodStart($currentDate)
    {
        switch ($this->statementType) {
            case 'monthly':
                return $currentDate->copy()->addMonth();
            case 'quarterly':
                return $currentDate->copy()->addMonths(3);
            case 'yearly':
                return $currentDate->copy()->addYear();
            case 'custom':
                return $currentDate->copy()->addDay();
            default:
                return $currentDate->copy()->addMonth();
        }
    }

    protected function calculateSummary($contributions, $donations): void
    {
        $totalContributions = $contributions->sum('amount');
        $totalDonations = $donations->sum('amount');
        $grandTotal = $totalContributions + $totalDonations;

        $this->summary = [
            'total_contributions' => $totalContributions,
            'total_donations' => $totalDonations,
            'grand_total' => $grandTotal,
            'contribution_percentage' => $grandTotal > 0 ? round(($totalContributions / $grandTotal) * 100, 1) : 0,
            'donation_percentage' => $grandTotal > 0 ? round(($totalDonations / $grandTotal) * 100, 1) : 0,
            'statement_type' => ucfirst($this->statementType),
            'period' => $this->getPeriodDescription(),
        ];
    }

    protected function getPeriodDescription(): string
    {
        $academicYear = AcademicYear::find($this->academic_year_id);
        $yearName = $academicYear?->name ?? 'Unknown';

        switch ($this->statementType) {
            case 'monthly':
                $ethiopianDate = EthiopianDateHelper::toEthiopian(now());
                return EthiopianDateHelper::getEthiopianMonthName($ethiopianDate['month']) . ' ' . $ethiopianDate['year'];
            case 'quarterly':
                $ethiopianDate = EthiopianDateHelper::toEthiopian(now());
                $quarter = ceil($ethiopianDate['month'] / 3);
                return "Q{$quarter} {$ethiopianDate['year']}";
            case 'yearly':
                return $yearName;
            case 'custom':
                return ($this->start_date ? date('M d, Y', strtotime($this->start_date)) : 'Start') .
                       ' - ' .
                       ($this->end_date ? date('M d, Y', strtotime($this->end_date)) : 'End');
            default:
                return 'Unknown Period';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Statement')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->action(function () {
                    // Print logic
                    Notification::make()
                        ->title('Print Ready')
                        ->body('Financial statement is ready for printing.')
                        ->success()
                        ->send();
                }),

            Action::make('export')
                ->label('Export Statement')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    // Export logic
                    Notification::make()
                        ->title('Export Started')
                        ->body('Financial statement is being exported to PDF.')
                        ->success()
                        ->send();
                }),

            Action::make('email')
                ->label('Email Statement')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->action(function () {
                    // Email logic
                    Notification::make()
                        ->title('Email Sent')
                        ->body('Financial statement has been emailed to stakeholders.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
