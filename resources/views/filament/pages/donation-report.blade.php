<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <form wire:submit.prevent="applyFilters" class="bg-white rounded-lg shadow p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Filters</h3>
            <div class="space-y-4">
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
                    <h3 class="text-lg font-semibold text-gray-900">Donation Report</h3>
                    <span class="text-sm text-gray-500">
                        {{ count($reportData['donations']) }} donations found
                    </span>
                </div>
            </x-slot>

            @if(empty($reportData['donations']))
                <div class="text-center py-8">
                    <heroicon-o-gift class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Donations Found</h3>
                    <p class="text-gray-600">No donations match the selected criteria.</p>
                </div>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Donor Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Recorded By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Notes
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($reportData['donations'] as $donation)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $donation->formatted_donor_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Birr {{ number_format($donation->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @match($donation->donation_type) {
                                                'General Fund' => 'bg-blue-100 text-blue-800',
                                                'Building Fund' => 'bg-green-100 text-green-800',
                                                'Missionary Support' => 'bg-purple-100 text-purple-800',
                                                'Charity/Aid' => 'bg-yellow-100 text-yellow-800',
                                                'Other' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            }
                                        ">
                                            {{ $donation->formatted_donation_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $donation->ethiopian_date }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $donation->recordedBy->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="truncate max-w-xs" title="{{ $donation->notes }}">
                                            {{ $donation->notes ?: '-' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Summary by Type -->
                @if(!empty($reportData['totalByType']))
                    <div class="mt-6 bg-white rounded-lg shadow p-6 border border-gray-200">
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Donation Summary by Type</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($reportData['totalByType'] as $typeData)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $typeData['type'] }}</span>
                                    </div>
                                    <span class="font-semibold text-green-600">
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
