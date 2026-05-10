<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header with Overview Stats --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Financial Analytics Overview
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <div class="text-sm text-blue-600 dark:text-blue-400">Total Revenue</div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">
                        Birr {{ number_format($analyticsData['overview']['total_revenue'] ?? 0, 2) }}
                    </div>
                    <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                        {{ $analyticsData['overview']['revenue_growth'] ?? 0 }}% growth
                    </div>
                </div>
                
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <div class="text-sm text-green-600 dark:text-green-400">Contributions</div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-300">
                        Birr {{ number_format($analyticsData['overview']['total_contributions'] ?? 0, 2) }}
                    </div>
                    <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                        {{ number_format($analyticsData['overview']['average_contribution'] ?? 0, 2) }} avg
                    </div>
                </div>
                
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                    <div class="text-sm text-purple-600 dark:text-purple-400">Donations</div>
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-300">
                        Birr {{ number_format($analyticsData['overview']['total_donations'] ?? 0, 2) }}
                    </div>
                    <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                        One-time contributions
                    </div>
                </div>
                
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                    <div class="text-sm text-orange-600 dark:text-orange-400">Participation Rate</div>
                    <div class="text-2xl font-bold text-orange-900 dark:text-orange-300">
                        {{ number_format($analyticsData['overview']['participation_rate'] ?? 0, 1) }}%
                    </div>
                    <div class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                        {{ $analyticsData['overview']['contributing_members'] ?? 0 }} / {{ $analyticsData['overview']['active_members'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Filters</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{ $this->form }}
            </div>
        </div>

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Trends Analysis --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Revenue Trends
                </h3>
                
                @if(!empty($analyticsData['trends']['monthly_trends']))
                    <div class="space-y-3">
                        @foreach($analyticsData['trends']['monthly_trends'] as $trend)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ date('F', mktime(0, 0, 0, $trend['month'], 1)) }}
                                </span>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white mr-2">
                                        Birr {{ number_format($trend['total'], 2) }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        ({{ $trend['count'] }} transactions)
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Growth Rate:</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ number_format($analyticsData['trends']['growth_rate'] ?? 0, 1) }}%
                            </span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="text-gray-500 dark:text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="text-sm">No trend data available</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Group Performance --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Group Performance
                </h3>
                
                @if(!empty($analyticsData['group_performance']))
                    <div class="space-y-3">
                        @foreach(array_slice($analyticsData['group_performance'], 0, 5) as $group)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $group['group_name'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $group['contributing_members'] }}/{{ $group['member_count'] }} members
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        Birr {{ number_format($group['total_contributions'], 2) }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format($group['participation_rate'], 1) }}% rate
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    @if(count($analyticsData['group_performance']) > 5)
                        <div class="mt-3 text-center">
                            <button class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                View All Groups
                            </button>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <div class="text-gray-500 dark:text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 4H15a5.002 5.002 0 019.288-4M3 12a3 3 0 106 0v-1a3 3 0 10-6 0v1a3 3 0 006 0z" />
                            </svg>
                            <p class="text-sm">No group performance data</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Top Contributors --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Top Contributors
            </h3>
            
            @if(!empty($analyticsData['top_contributors']))
                <div class="space-y-3">
                    @foreach($analyticsData['top_contributors'] as $index => $contributor)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-xs font-medium text-indigo-600 dark:text-indigo-300">
                                        {{ $index + 1 }}
                                    </span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $contributor['member']['full_name'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $contributor['member']['currentGroupAssignment']['group']['name'] ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    Birr {{ number_format($contributor['total_amount'], 2) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $contributor['contribution_count'] }} contributions
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <p class="text-sm">No contributor data available</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Monthly Breakdown --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Monthly Breakdown
            </h3>
            
            @if(!empty($analyticsData['monthly_breakdown']))
                <div class="space-y-3">
                    @foreach($analyticsData['monthly_breakdown'] as $month)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $month['month_name'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $month['count'] }} contributions
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    Birr {{ number_format($month['total'], 2) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Avg: {{ $month['count'] > 0 ? number_format($month['total'] / $month['count'], 2) : 0 }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm">No monthly breakdown data</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Payment Patterns --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Payment Patterns
            </h3>
            
            @if(!empty($analyticsData['payment_patterns']))
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Payment Methods --}}
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Payment Methods</h4>
                        <div class="space-y-2">
                            @foreach($analyticsData['payment_patterns']['payment_methods'] as $method)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ ucfirst($method['payment_method']) }}
                                    </span>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            Birr {{ number_format($method['total'], 2) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $method['count'] }} transactions
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Payment Timing --}}
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Payment Timing</h4>
                        <div class="space-y-2">
                            @foreach(array_slice($analyticsData['payment_patterns']['payment_timing'], 0, 10) as $timing)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        Day {{ $timing['day_of_month'] }}
                                    </span>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $timing['count'] }} payments
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm">No payment pattern data</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Charts Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Revenue Trend
                </h3>
                <div class="h-48 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded">
                    <div class="text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p class="text-sm">Revenue trend chart</p>
                        <p class="text-xs mt-1">Chart integration needed</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Group Comparison
                </h3>
                <div class="h-48 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded">
                    <div class="text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm">Group comparison chart</p>
                        <p class="text-xs mt-1">Revenue by group</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Monthly Distribution
                </h3>
                <div class="h-48 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded">
                    <div class="text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                        <p class="text-sm">Monthly distribution chart</p>
                        <p class="text-xs mt-1">Revenue by month</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Analytics Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button wire:click="$dispatch('exportAnalytics')" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                    Export Analytics Report
                </button>
                <button wire:click="$dispatch('scheduleReport')" 
                        class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                    Schedule Monthly Report
                </button>
                <button wire:click="$dispatch('refreshData')" 
                        class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                    Refresh Data
                </button>
                <button wire:click="$dispatch('shareReport')" 
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                    Share with Leadership
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
