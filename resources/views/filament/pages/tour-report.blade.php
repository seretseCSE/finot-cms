<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Report Filters</h3>
            <form wire:submit.prevent="generateReport">
                {{ $this->form }}
                <div class="mt-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <x-filament::loading-indicator class="mr-2" />
                        Generate Report
                    </x-filament::button>
                </div>
            </form>
        </div>

        <!-- Report Metrics -->
        @php
            $metrics = $this->getReportMetrics();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-map" class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Tours</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $metrics['total_tours'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-check-circle" class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Completed Tours</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $metrics['completed_tours'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-users" class="w-6 h-6 text-purple-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Passengers</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $metrics['total_passengers'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-banknotes" class="w-6 h-6 text-yellow-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">ETB {{ number_format($metrics['total_revenue'], 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-user-check" class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Confirmed Passengers</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $metrics['confirmed_passengers'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-check-circle" class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Attended Passengers</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $metrics['attended_passengers'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-orange-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-chart-bar" class="w-6 h-6 text-orange-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Avg Attendance Rate</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($metrics['average_attendance_rate'], 1) }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tours Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Tour Details</h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
