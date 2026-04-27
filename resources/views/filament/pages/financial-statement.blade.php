<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Period Selection Form -->
        <form wire:submit.prevent="generateStatement" class="bg-white rounded-lg shadow p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Financial Statement Generation</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Period Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period Type</label>
                    <select wire:model="periodType" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>

                {{-- Year --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select wire:model="selectedYear" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @for($year = now()->year - 5; $year <= now()->year + 1; $year++)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endfor
                    </select>
                </div>

                {{-- Period (Month/Quarter) --}}
                <div wire:loading wire:target="periodType">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period</label>
                    <div class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-500">Loading...</div>
                </div>

                {{-- Month Selection --}}
                <div wire:ignore wire:init
                     x-data="{ periodType: @entangle('periodType') }"
                     x-show="periodType === 'monthly'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select wire:model="selectedMonth" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach(\App\Helpers\EthiopianDateHelper::getMonthsForContribution() as $key => $month)
                            <option value="{{ $key }}">{{ $month }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Quarter Selection --}}
                <div wire:ignore wire:init
                     x-data="{ periodType: @entangle('periodType') }"
                     x-show="periodType === 'quarterly'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quarter</label>
                    <select wire:model="selectedQuarter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1">Q1 (Jan-Mar)</option>
                        <option value="2">Q2 (Apr-Jun)</option>
                        <option value="3">Q3 (Jul-Sep)</option>
                        <option value="4">Q4 (Oct-Dec)</option>
                    </select>
                </div>
            </div>
            <div class="mt-6">
                <x-filament::actions :actions="$this->getActions()" />
            </div>
        </form>

        <!-- Error Display -->
        @if($errors->has('generation_error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <h4 class="text-red-800 font-medium">Generation Error</h4>
                        <p class="text-red-600 text-sm">{{ $errors->first('generation_error') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
