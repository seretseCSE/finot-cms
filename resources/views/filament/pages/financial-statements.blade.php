<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header with Statement Type Selection --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Financial Statements
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{ $this->form }}
            </div>
        </div>

        {{-- Statement Summary --}}
        @if(!empty($summary))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $summary['statement_type'] }} Statement Summary
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Period: {{ $summary['period'] }}
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div class="text-sm text-blue-600 dark:text-blue-400">Total Contributions</div>
                        <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">
                            Birr {{ number_format($summary['total_contributions'], 2) }}
                        </div>
                        <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                            {{ number_format($summary['contribution_percentage'], 1) }}% of total
                        </div>
                    </div>
                    
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                        <div class="text-sm text-green-600 dark:text-green-400">Total Donations</div>
                        <div class="text-2xl font-bold text-green-900 dark:text-green-300">
                            Birr {{ number_format($summary['total_donations'], 2) }}
                        </div>
                        <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                            {{ number_format($summary['donation_percentage'], 1) }}% of total
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                        <div class="text-sm text-purple-600 dark:text-purple-400">Grand Total</div>
                        <div class="text-2xl font-bold text-purple-900 dark:text-purple-300">
                            Birr {{ number_format($summary['grand_total'], 2) }}
                        </div>
                        <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                            All revenue streams
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                        <div class="text-sm text-orange-600 dark:text-orange-400">Revenue Mix</div>
                        <div class="text-lg font-bold text-orange-900 dark:text-orange-300">
                            {{ number_format($summary['contribution_percentage'], 1) }}% / {{ number_format($summary['donation_percentage'], 1) }}%
                        </div>
                        <div class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                            Contributions / Donations
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Statement Details --}}
        @if(!empty($statementData))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white p-6 pb-0">
                    Statement Details
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Period
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Contributions
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Donations
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Total Revenue
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Growth
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($statementData as $index => $period)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $period['period'] }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            Birr {{ number_format($period['total_contributions'], 2) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            Birr {{ number_format($period['total_donations'], 2) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            Birr {{ number_format($period['grand_total'], 2) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($index > 0)
                                            <?php 
                                            $previousTotal = $statementData[$index-1]['grand_total'];
                                            $growth = $previousTotal > 0 ? (($period['grand_total'] - $previousTotal) / $previousTotal) * 100 : 0;
                                            ?>
                                            <div class="text-sm font-medium {{ $growth >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $growth >= 0 ? '+' : '' }}{{ number_format($growth, 1) }}%
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                -
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mr-2">
                                            View Details
                                        </button>
                                        <button class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">
                                            Export
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No statement data available</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Please select academic year and statement type to generate a financial statement.
                    </p>
                </div>
            </div>
        @endif

        {{-- Charts Section --}}
        @if(!empty($statementData))
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Revenue Trend Chart --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Revenue Trend
                    </h3>
                    <div class="h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded">
                        <div class="text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="text-sm">Revenue trend chart will be displayed here</p>
                            <p class="text-xs mt-1">Integration with charting library needed</p>
                        </div>
                    </div>
                </div>

                {{-- Revenue Distribution Chart --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Revenue Distribution
                    </h3>
                    <div class="h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded">
                        <div class="text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                            <p class="text-sm">Revenue distribution chart will be displayed here</p>
                            <p class="text-xs mt-1">Contributions vs Donations breakdown</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Actions Section --}}
        @if(!empty($statementData))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Statement Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <button wire:click="$dispatch('printStatement')" 
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                        Print Statement
                    </button>
                    <button wire:click="$dispatch('exportStatement')" 
                            class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                        Export to PDF
                    </button>
                    <button wire:click="$dispatch('emailStatement')" 
                            class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                        Email to Stakeholders
                    </button>
                    <button wire:click="$dispatch('scheduleStatement')" 
                            class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                        Schedule Regular Reports
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
