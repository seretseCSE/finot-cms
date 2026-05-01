<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Report Filters</x-slot>
            <div class="space-y-4">
                {{ $this->form }}
            </div>
            <div class="mt-4 flex gap-2">
                <x-filament::button wire:click="applyFilters">
                    Apply Filters
                </x-filament::button>
                <x-filament::button wire:click="resetFilters" color="gray">
                    Reset Filters
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <span>Donation Report</span>
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        {{ count($reportData['donations']) }} donations found
                    </span>
                </div>
            </x-slot>

            @if(empty($reportData['donations']))
                <div class="text-center py-8">
                    <x-heroicon-o-gift class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Donations Found</h3>
                    <p class="text-gray-600 dark:text-gray-400">No donations match the selected criteria.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Donor Name</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recorded By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($reportData['donations'] as $donation)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $donation->formatted_donor_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                                        Birr {{ number_format($donation->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ match($donation->donation_type) {
                                            'General Fund'       => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                            'Building Fund'      => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                            'Missionary Support' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                            'Charity/Aid'        => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                            default              => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        } }}">
                                            {{ $donation->formatted_donation_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $donation->ethiopian_date }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $donation->recordedBy->name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ $donation->notes }}">
                                        {{ $donation->notes ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!empty($reportData['totalByType']))
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Donation Summary by Type</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($reportData['totalByType'] as $typeData)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $typeData['type'] }}</span>
                                    <span class="font-semibold text-green-600 dark:text-green-400">
                                        Birr {{ number_format($typeData['total'], 2) }}
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
