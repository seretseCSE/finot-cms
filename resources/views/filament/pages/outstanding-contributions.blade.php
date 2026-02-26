<x-filament-panels::page>
    <div class="space-y-6">
        @if(!$activeYear)
            <x-filament::section>
                <div class="text-center py-8">
                    <heroicon-o-exclamation-triangle class="w-12 h-12 text-yellow-500 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Active Academic Year</h3>
                    <p class="text-gray-600">There is currently no active academic year. Please contact Education Head to activate an academic year.</p>
                </div>
            </x-filament::section>
        @else
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <heroicon-o-calculator class="w-6 h-6 text-blue-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Expected</p>
                            <p class="text-2xl font-semibold text-gray-900">Birr {{ number_format($summaryData['total_expected'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <heroicon-o-banknotes class="w-6 h-6 text-green-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Collected</p>
                            <p class="text-2xl font-semibold text-gray-900">Birr {{ number_format($summaryData['total_collected'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 rounded-lg p-3">
                            <heroicon-o-exclamation-circle class="w-6 h-6 text-red-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Outstanding</p>
                            <p class="text-2xl font-semibold text-red-600">Birr {{ number_format($summaryData['total_outstanding'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                            <heroicon-o-chart-pie class="w-6 h-6 text-purple-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Collection Rate</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $summaryData['collection_rate'] }}%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Form -->
            <form wire:submit.prevent="refreshTable" class="bg-white rounded-lg shadow p-6 border border-gray-200 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{ $this->getFormSchema() }}
                </div>
                <div class="mt-4">
                    <button type="submit" class="filament-button filament-button-primary">
                        Apply Filters
                    </button>
                    <button type="button" wire:click="resetFilters" class="filament-button filament-button-secondary ml-2">
                        Reset
                    </button>
                </div>
            </form>

            <!-- Outstanding Contributions Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Outstanding Contributions - {{ $activeYear->name }}
                        </h3>
                        <span class="text-sm text-gray-500">
                            {{ count($tableData) }} members with outstanding amounts
                        </span>
                    </div>
                </x-slot>

                @if(empty($tableData))
                    <div class="text-center py-8">
                        <heroicon-o-check-circle class="w-12 h-12 text-green-500 mx-auto mb-4" />
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">All Caught Up!</h3>
                        <p class="text-gray-600">No outstanding contributions found for the selected criteria.</p>
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
                                        Member Code
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Group
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Month
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Expected Amount
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Paid Amount
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Outstanding Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tableData as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $row['member']->full_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $row['member']->member_code }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $row['member']->memberGroup->name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $row['month'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            Birr {{ number_format($row['expected'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            Birr {{ number_format($row['paid'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600 text-right">
                                            Birr {{ number_format($row['outstanding'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
