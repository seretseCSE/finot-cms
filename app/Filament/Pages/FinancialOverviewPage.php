<?php

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\FinancialTransaction;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class FinancialOverviewPage extends Page
{
    protected string $view = 'filament.pages.financial-overview';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-pie';
    }

    public static function getNavigationLabel(): string
    {
        return 'Financial Overview';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public $selectedPeriod = 'current_month';

    public $selectedBank = 'all';

    public function mount(): void
    {
        $this->selectedPeriod = 'current_month';
        $this->selectedBank = 'all';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public function getFinancialData(): array
    {
        try {
            $period = $this->getDateRange();

            // Financial Transactions
            $transactions = FinancialTransaction::whereBetween('transaction_date', [$period['start'], $period['end']])
                ->when($this->selectedBank !== 'all', function ($query) {
                    return $query->where('bank_account_id', $this->selectedBank);
                })
                ->approved();

            $totalIncome = $transactions->income()->sum('amount');
            $totalExpenses = $transactions->expense()->sum('amount');
            $netProfit = $totalIncome - $totalExpenses;

            // Contributions
            $contributions = Contribution::whereBetween('payment_date', [$period['start'], $period['end']])
                ->sum('amount');

            // Donations
            $donations = Donation::whereBetween('donation_date', [$period['start'], $period['end']])
                ->sum('amount');

            // Total funds
            $totalFunds = $totalIncome + $contributions + $donations;

            // Bank balances
            $bankBalances = BankAccount::active()
                ->when($this->selectedBank !== 'all', function ($query) {
                    return $query->where('id', $this->selectedBank);
                })
                ->sum('current_balance');

            return [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'contributions' => $contributions,
                'donations' => $donations,
                'total_funds' => $totalFunds,
                'bank_balances' => $bankBalances,
                'total_available' => $bankBalances + $netProfit,
            ];
        } catch (\Exception $e) {
            return [
                'total_income' => 0,
                'total_expenses' => 0,
                'net_profit' => 0,
                'contributions' => 0,
                'donations' => 0,
                'total_funds' => 0,
                'bank_balances' => 0,
                'total_available' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getBankAccounts(): array
    {
        try {
            return BankAccount::active()
                ->orderBy('bank_name')
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name', 'current_balance'])
                ->map(function ($account) {
                    $account->formatted_balance = number_format($account->current_balance, 2).' ETB';
                    $account->full_name = $account->bank_name.' - '.$account->account_name;

                    return $account;
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRecentTransactions(): array
    {
        try {
            return FinancialTransaction::with(['bankAccount', 'recordedBy'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'transaction_id' => $transaction->transaction_id,
                        'type' => $transaction->type,
                        'title' => $transaction->title,
                        'amount' => number_format($transaction->amount, 2).' ETB',
                        'category' => $transaction->category,
                        'bank_account' => $transaction->bankAccount?->account_name,
                        'date' => $transaction->transaction_date->format('M d, Y'),
                        'status' => $transaction->status,
                        'recorded_by' => $transaction->recordedBy?->name,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getTopExpenses(): array
    {
        try {
            return FinancialTransaction::expense()
                ->approved()
                ->whereBetween('transaction_date', [$this->getDateRange()['start'], $this->getDateRange()['end']])
                ->orderBy('amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'title' => $transaction->title,
                        'category' => $transaction->category,
                        'amount' => number_format($transaction->amount, 2).' ETB',
                        'date' => $transaction->transaction_date->format('M d, Y'),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getTopIncome(): array
    {
        try {
            return FinancialTransaction::income()
                ->approved()
                ->whereBetween('transaction_date', [$this->getDateRange()['start'], $this->getDateRange()['end']])
                ->orderBy('amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'title' => $transaction->title,
                        'category' => $transaction->category,
                        'source' => $transaction->source,
                        'amount' => number_format($transaction->amount, 2).' ETB',
                        'date' => $transaction->transaction_date->format('M d, Y'),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getDateRange(): array
    {
        $now = now();

        switch ($this->selectedPeriod) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];
            case 'current_week':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek(),
                ];
            case 'current_month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];
            case 'current_quarter':
                return [
                    'start' => $now->copy()->startOfQuarter(),
                    'end' => $now->copy()->endOfQuarter(),
                ];
            case 'current_year':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear(),
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => null),
        ];
    }
}
