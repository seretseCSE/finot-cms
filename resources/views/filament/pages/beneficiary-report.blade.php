<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <form wire:submit.prevent="applyFilters" class="bg-white rounded-lg shadow p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Filters</h3>
            <div class="space-y-4">
                {{ $this->form }}
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

        <!-- Beneficiaries Table -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Beneficiaries</h3>
                    <span class="text-sm text-gray-500">
                        {{ count($reportData['beneficiaries']) }} beneficiaries found
                    </span>
                </div>
            </x-slot>

            @if(empty($reportData['beneficiaries']))
                <div class="text-center py-8">
                    <heroicon-o-users class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Beneficiaries Found</h3>
                    <p class="text-gray-600">No beneficiaries match the selected criteria.</p>
                </div>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Code
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Full Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Need Category
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Aid Received
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Last Distribution
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($reportData['beneficiaries'] as $beneficiary)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $beneficiary->beneficiary_code }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $beneficiary->full_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $beneficiary->type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ $beneficiary->need_category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Birr {{ number_format($beneficiary->total_aid_received, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $beneficiary->last_distribution_date ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @match($beneficiary->status) {
                                                'Active' => 'bg-green-100 text-green-800',
                                                'Inactive' => 'bg-yellow-100 text-yellow-800',
                                                'Completed' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            }
                                        ">
                                            {{ $beneficiary->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <!-- Aid Distributions Table -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Aid Distributions</h3>
                    <span class="text-sm text-gray-500">
                        {{ count($reportData['aidDistributions']) }} distributions found
                    </span>
                </div>
            </x-slot>

            @if(empty($reportData['aidDistributions']))
                <div class="text-center py-8">
                    <heroicon-o-hand-raised class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Distributions Found</h3>
                    <p class="text-gray-600">No aid distributions match the selected criteria.</p>
                </div>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Beneficiary
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aid Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Receipt Number
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Distributed By
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($reportData['aidDistributions'] as $distribution)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $distribution->distribution_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $distribution->beneficiary?->full_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ $distribution->aid_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Birr {{ number_format($distribution->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $distribution->receipt_number ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $distribution->distributedBy?->name ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <!-- Aid Summary by Type -->
        @if(!empty($reportData['aidByType']))
            <div class="mt-6 bg-white rounded-lg shadow p-6 border border-gray-200">
                <h4 class="text-md font-semibold text-gray-900 mb-4">Aid Summary by Type</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($reportData['aidByType'] as $typeData)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-medium text-gray-900">{{ $typeData['type'] }}</span>
                                <span class="text-xs text-gray-500">({{ $typeData['count'] }} distributions)</span>
                            </div>
                            <span class="font-semibold text-green-600">
                                Birr {{ number_format($typeData['total'], 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
