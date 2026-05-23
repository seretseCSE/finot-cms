<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Monthly Contribution Reports
                    </h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        View detailed monthly contribution reports with filtering options
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    @if($monthlyReports->isNotEmpty())
                        <div class="text-right">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ $monthlyReports->count() }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Months with Contributions
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <x-filament::card class="shadow-xl border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h2>
            </div>
            <div class="p-6">
                {{ $this->form }}
            </div>
        </x-filament::card>

        {{-- Monthly Reports --}}
        @if($monthlyReports->isNotEmpty())
            @foreach($monthlyReports as $monthData)
                <x-filament::card class="shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-900">
                    <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ $monthData['month_name'] }}
                                </h3>
                                <p class="mt-2 text-gray-600 dark:text-gray-400">
                                    {{ $monthData['member_count'] }} members • {{ number_format($monthData['total_amount'], 2) }} Birr total
                                </p>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {{ number_format($monthData['total_amount'], 2) }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Total Amount
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                        {{ $monthData['member_count'] }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Members
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Member Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Member Code
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Department
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Group
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Member Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Payment Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Payment Method
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($monthData['contributions'] as $contribution)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $contribution->member->first_name }} {{ $contribution->member->father_name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $contribution->member->member_code }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $contribution->member->department?->name_en ?? 'No Department' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $contribution->member->currentGroupAssignment?->group?->name ?? 'No Group' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    {{ ucfirst($contribution->member->member_type) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $contribution->payment_date ? $contribution->payment_date->format('M d, Y') : 'Not set' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    {{ ucfirst($contribution->payment_method) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="text-sm font-medium text-green-600 dark:text-green-400">
                                                {{ number_format($contribution->amount, 2) }} Birr
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::card>
            @endforeach
        @else
            <x-filament::card class="shadow-xl border border-gray-100 dark:border-gray-700">
                <div class="py-24 text-center">
                    <div class="mx-auto w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-2xl flex items-center justify-center mb-6">
                        <x-tour-icon name="education" size="24" class="" style="color:#9ca3af" aria-hidden="true" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        No Contribution Reports Found
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400">
                        Try adjusting the filters or select a different academic year to see contribution reports.
                    </p>
                </div>
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>
