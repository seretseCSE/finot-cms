<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Report Filters</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <input type="date" wire:model.live="date_from" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>

                <div>
                    <input type="date" wire:model.live="date_to" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>

                <div>
                    <select wire:model.live="aid_type" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">All Aid Types</option>
                        @foreach(\App\Models\AidDistribution::distinct()->pluck('aid_type') as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        @php $beneficiaryData = $this->getBeneficiaryData(); @endphp

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Beneficiaries</p>
                <p class="text-2xl font-bold">{{ $beneficiaryData['total'] ?? 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Active</p>
                <p class="text-2xl font-bold text-green-600">{{ $beneficiaryData['active'] ?? 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Inactive</p>
                <p class="text-2xl font-bold text-gray-600">{{ $beneficiaryData['inactive'] ?? 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Completed</p>
                <p class="text-2xl font-bold text-blue-600">{{ $beneficiaryData['completed'] ?? 0 }}</p>
            </div>
        </div>

        @php $distributionData = $this->getDistributionData(); @endphp

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Distributions</p>
                <p class="text-2xl font-bold">{{ $distributionData['total_distributions'] ?? 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Amount</p>
                <p class="text-2xl font-bold text-green-600">ETB {{ number_format($distributionData['total_amount'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Average Amount</p>
                <p class="text-2xl font-bold text-blue-600">ETB {{ number_format($distributionData['average_amount'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Locked Records</p>
                <p class="text-2xl font-bold text-red-600">{{ $distributionData['locked_count'] ?? 0 }}</p>
            </div>
        </div>

        @if(!empty($distributionData['by_type']))
            <x-filament::section>
                <x-slot name="heading">Distributions by Aid Type</x-slot>
                <div class="space-y-2">
                    @foreach($distributionData['by_type'] as $type => $info)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                            <span class="font-medium">{{ $type }}</span>
                            <div class="text-right">
                                <span class="text-blue-600 font-bold">{{ $info['count'] }} records</span>
                                <span class="text-gray-500 ml-2">ETB {{ number_format($info['total'], 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if(!empty($distributionData['monthly_trend']))
            <x-filament::section>
                <x-slot name="heading">Monthly Trend</x-slot>
                <div class="space-y-2">
                    @foreach($distributionData['monthly_trend'] as $month => $info)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                            <span class="font-medium">{{ $month }}</span>
                            <div class="text-right">
                                <span class="text-blue-600 font-bold">{{ $info['count'] }} records</span>
                                <span class="text-gray-500 ml-2">ETB {{ number_format($info['total'], 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
