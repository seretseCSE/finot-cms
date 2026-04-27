<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Period</label>
                    <select wire:model.live="selectedPeriod" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="today">Today</option>
                        <option value="current_week">This Week</option>
                        <option value="current_month">This Month</option>
                        <option value="current_quarter">This Quarter</option>
                        <option value="current_year">This Year</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bank Account</label>
                    <select wire:model.live="selectedBank" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="all">All Banks</option>
                        @foreach($this->getBankAccounts() as $account)
                            <option value="{{ $account['id'] }}">{{ $account['full_name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Income -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-success-50 dark:bg-success-500/10 rounded-full flex items-center justify-center">
                            <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-success-600 dark:text-success-400" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Income</p>
                        <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['total_income'], 2) }} ETB
                        </p>
                    </div>
                </div>
            </div>

            <!-- Total Expenses -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-danger-50 dark:bg-danger-500/10 rounded-full flex items-center justify-center">
                            <x-heroicon-o-arrow-trending-down class="w-4 h-4 text-danger-600 dark:text-danger-400" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Expenses</p>
                        <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['total_expenses'], 2) }} ETB
                        </p>
                    </div>
                </div>
            </div>

            <!-- Net Profit -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-primary-50 dark:bg-primary-500/10 rounded-full flex items-center justify-center">
                            <x-heroicon-o-scale class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Profit</p>
                        <p class="text-2xl font-semibold {{ $this->getFinancialData()['net_profit'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ number_format($this->getFinancialData()['net_profit'], 2) }} ETB
                        </p>
                    </div>
                </div>
            </div>

            <!-- Total Available -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-warning-50 dark:bg-warning-500/10 rounded-full flex items-center justify-center">
                            <x-heroicon-o-banknotes class="w-4 h-4 text-warning-600 dark:text-warning-400" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Available</p>
                        <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['total_available'], 2) }} ETB
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Funds & Balances -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Contributions & Donations -->
            <x-filament::section heading="Additional Funds">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Member Contributions</span>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['contributions'], 2) }} ETB
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Donations</span>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['donations'], 2) }} ETB
                        </span>
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-white/10">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Additional Funds</span>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['contributions'] + $this->getFinancialData()['donations'], 2) }} ETB
                        </span>
                    </div>
                </div>
            </x-filament::section>

            <!-- Bank Balances -->
            <x-filament::section heading="Bank Balances">
                <div class="space-y-3">
                    @forelse($this->getBankAccounts() as $account)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $account['full_name'] }}</span>
                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $account['formatted_balance'] }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 dark:text-gray-400">No active bank accounts found.</div>
                    @endforelse
                    <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-white/10">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Bank Balance</span>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ number_format($this->getFinancialData()['bank_balances'], 2) }} ETB
                        </span>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- Recent Transactions & Top Items -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Transactions -->
            <div class="lg:col-span-2">
                <x-filament::section heading="Recent Transactions">
                    <div class="overflow-x-auto -mx-6 -mb-6">
                        <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-6 py-3 text-sm font-semibold text-gray-950 dark:text-white">Transaction</th>
                                    <th class="px-6 py-3 text-sm font-semibold text-gray-950 dark:text-white">Type</th>
                                    <th class="px-6 py-3 text-sm font-semibold text-gray-950 dark:text-white">Amount</th>
                                    <th class="px-6 py-3 text-sm font-semibold text-gray-950 dark:text-white">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5 whitespace-nowrap">
                                @forelse($this->getRecentTransactions() as $transaction)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $transaction['title'] }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $transaction['transaction_id'] }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <x-filament::badge :color="$transaction['type'] === 'income' ? 'success' : 'danger'">
                                                {{ ucfirst($transaction['type']) }}
                                            </x-filament::badge>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-950 dark:text-white">{{ $transaction['amount'] }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $transaction['date'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-sm text-center text-gray-500 dark:text-gray-400">
                                            No recent transactions found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>

            <!-- Top Income & Expenses -->
            <div class="space-y-6">
                <!-- Top Income -->
                <x-filament::section heading="Top Income">
                    <div class="space-y-4">
                        @forelse($this->getTopIncome() as $income)
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $income['title'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $income['category'] }}</div>
                                </div>
                                <div class="text-sm font-medium text-success-600 dark:text-success-400 ml-4 whitespace-nowrap">{{ $income['amount'] }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">No income entries found.</div>
                        @endforelse
                    </div>
                </x-filament::section>

                <!-- Top Expenses -->
                <x-filament::section heading="Top Expenses">
                    <div class="space-y-4">
                        @forelse($this->getTopExpenses() as $expense)
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $expense['title'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $expense['category'] }}</div>
                                </div>
                                <div class="text-sm font-medium text-danger-600 dark:text-danger-400 ml-4 whitespace-nowrap">{{ $expense['amount'] }}</div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">No expense entries found.</div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
