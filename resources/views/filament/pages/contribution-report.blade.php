<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <form wire:submit.prevent="applyFilters" class="bg-white rounded-lg shadow p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Filters</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {{ $this->getFormSchema() }}
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="filament-button filament-button-primary">
                    Apply Filters
                </button>
                <button type="button" wire:click="resetFilters" class="filament-button filament-button-secondary">
                    Reset Filters
                </button>
            </div>
        </form>

        <!-- Report Data Table -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Contribution Report
                        @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                            - {{ $academicYears[$selectedAcademicYear] ?? 'All Years' }}
                        @endif
                    </h3>
                    <span class="text-sm text-gray-500">
                        {{ count($reportData['contributions']) }} contributions found
                    </span>
                </div>
            </x-slot>

            @if(empty($reportData['contributions']))
                <div class="text-center py-8">
                    <heroicon-o-document-text class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Contributions Found</h3>
                    <p class="text-gray-600">No contributions match the selected criteria.</p>
                </div>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Member Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Group
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Month
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment Method
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Recorded By
                                </th>
                                @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($reportData['contributions'] as $contribution)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $contribution->member->full_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $contribution->member->memberGroup->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $contribution->month_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Birr {{ number_format($contribution->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $contribution->formatted_payment_method }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ app(\App\Helpers\EthiopianDateHelper::class)->toEthiopian($contribution->payment_date)['month_name_am'] . ' ' . app(\App\Helpers\EthiopianDateHelper::class)->toEthiopian($contribution->payment_date)['day'] . ', ' . app(\App\Helpers\EthiopianDateHelper::class)->toEthiopian($contribution->payment_date)['year'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $contribution->recordedBy->name }}
                                    </td>
                                    @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($contribution->is_archived)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    Archived
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Top Contributors Summary -->
                @if(!empty($reportData['topContributors']))
                    <div class="mt-6 bg-white rounded-lg shadow p-6 border border-gray-200">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Top 5 Contributors</h4>
                        <div class="space-y-2">
                            @foreach($reportData['topContributors'] as $index => $contributor)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $contributor['member']->full_name }}</span>
                                        <span class="text-sm text-gray-500 ml-2">{{ $contributor['member']->memberGroup->name ?? '' }}</span>
                                    </div>
                                    <span class="font-semibold text-green-600">
                                        Birr {{ number_format($contributor['total'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
