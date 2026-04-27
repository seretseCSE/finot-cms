<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <x-filament::section>
            <x-slot name="heading">Report Filters</x-slot>

            <form wire:submit.prevent="applyFilters">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{ $this->form }}
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">
                        Apply Filters
                    </x-filament::button>

                    <x-filament::button type="button" wire:click="resetFilters" color="gray" icon="heroicon-m-arrow-path">
                        Reset
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if(!empty($reportData['contributions']))
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Contributions</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ count($reportData['contributions']) }}</p>
                        </div>
                        <div class="p-2 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Amount</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">Birr {{ number_format($reportData['contributions']->sum('amount'), 0) }}</p>
                        </div>
                        <div class="p-2 bg-success-50 dark:bg-success-900/20 rounded-lg">
                            <svg class="w-5 h-5 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Average Contribution</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">Birr {{ number_format($reportData['contributions']->avg('amount'), 0) }}</p>
                        </div>
                        <div class="p-2 bg-info-50 dark:bg-info-900/20 rounded-lg">
                            <svg class="w-5 h-5 text-info-600 dark:text-info-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Unique Contributors</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ $reportData['contributions']->pluck('member_id')->unique()->count() }}</p>
                        </div>
                        <div class="p-2 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                            <svg class="w-5 h-5 text-warning-600 dark:text-warning-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Methods & Group Distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <x-filament::section>
                    <x-slot name="heading">Payment Methods</x-slot>
                    <div class="space-y-2">
                        @php $paymentMethods = $reportData['contributions']->groupBy('payment_method'); @endphp
                        @foreach($paymentMethods as $method => $contributions)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-white dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700">
                                        @if($method === 'cash')
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        @elseif($method === 'bank')
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $contributions->first()->formatted_payment_method }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $contributions->count() }} transactions</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Birr {{ number_format($contributions->sum('amount'), 0) }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ round(($contributions->sum('amount') / $reportData['contributions']->sum('amount')) * 100, 1) }}%</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Group Distribution</x-slot>
                    <div class="space-y-2">
                        @php
                            $groups = $reportData['contributions']->groupBy(function($contribution) {
                                return $contribution->member->memberGroup?->name ?? 'Unknown';
                            });
                        @endphp
                        @foreach($groups->take(5) as $groupName => $contributions)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $groupName }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $contributions->pluck('member_id')->unique()->count() }} members</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Birr {{ number_format($contributions->sum('amount'), 0) }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $contributions->count() }} contributions</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>
        @endif

        <!-- Contribution Details Table -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <span>Contribution Details</span>
                        @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">- {{ $academicYears[$selectedAcademicYear] ?? 'All Years' }}</span>
                        @endif
                    </div>
                    <x-filament::badge color="primary" size="sm">
                        {{ count($reportData['contributions']) }} contributions
                    </x-filament::badge>
                </div>
            </x-slot>

            @if(empty($reportData['contributions']))
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <svg class="w-8 h-8 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-base font-medium text-gray-900 dark:text-white">No Contributions Found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No contributions match the selected criteria.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600 dark:text-gray-400">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">Member</th>
                                <th scope="col" class="px-4 py-3 font-medium">Group</th>
                                <th scope="col" class="px-4 py-3 font-medium">Month</th>
                                <th scope="col" class="px-4 py-3 font-medium text-right">Amount</th>
                                <th scope="col" class="px-4 py-3 font-medium">Payment</th>
                                <th scope="col" class="px-4 py-3 font-medium">Date</th>
                                <th scope="col" class="px-4 py-3 font-medium">Recorded By</th>
                                @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($reportData['contributions'] as $contribution)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 h-7 w-7 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs font-medium text-gray-700 dark:text-gray-300">
                                                {{ $contribution->member ? substr($contribution->member->full_name, 0, 1) : '?' }}
                                            </div>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $contribution->member ? $contribution->member->full_name : 'Unknown Member' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-filament::badge color="gray" size="sm">
                                            {{ $contributor->member->memberGroup?->name ?? 'N/A' }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $contribution->month_name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-semibold text-gray-900 dark:text-white">Birr {{ number_format($contribution->amount, 2) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $contribution->formatted_payment_method }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @php
                                            $ethDate = app(\App\Helpers\EthiopianDateHelper::class)->toEthiopian($contribution->payment_date);
                                        @endphp
                                        {{ $ethDate['month_name_am'] . ' ' . $ethDate['day'] . ', ' . $ethDate['year'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $contribution->recordedBy->name }}</td>
                                    @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($contribution->is_archived)
                                                <x-filament::badge color="gray" size="sm">Archived</x-filament::badge>
                                            @else
                                                <x-filament::badge color="success" size="sm">Active</x-filament::badge>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if(!empty($reportData['topContributors']))
            <x-filament::section>
                <x-slot name="heading">Top Contributors</x-slot>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    @foreach($reportData['topContributors'] as $index => $contributor)
                        <div class="flex flex-col items-center text-center p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900">
                            <div class="mb-2 text-xs font-semibold text-gray-400 dark:text-gray-500">#{{ $index + 1 }}</div>
                            <div class="h-9 w-9 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                                {{ $contributor['member'] ? substr($contributor['member']->full_name, 0, 1) : '?' }}
                            </div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate w-full">{{ $contributor['member'] ? $contributor['member']->full_name : 'Unknown' }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $contributor['member']->memberGroup?->name ?? '' }}</p>
                            <div class="mt-auto w-full bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                                <p class="text-base font-bold text-gray-900 dark:text-white">Birr {{ number_format($contributor['total'], 0) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
