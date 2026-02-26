@if($activeYear)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Total Expected -->
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 rounded-lg p-2">
                    <heroicon-o-calculator class="w-5 h-5 text-blue-600" />
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Expected</p>
                    <p class="text-lg font-semibold text-gray-900">Birr {{ number_format($data['total_expected'], 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Total Collected -->
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-100 rounded-lg p-2">
                    <heroicon-o-banknotes class="w-5 h-5 text-green-600" />
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Collected</p>
                    <p class="text-lg font-semibold text-gray-900">Birr {{ number_format($data['total_collected'], 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Total Outstanding -->
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-red-100 rounded-lg p-2">
                    <heroicon-o-exclamation-circle class="w-5 h-5 text-red-600" />
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Outstanding</p>
                    <p class="text-lg font-semibold text-red-600">Birr {{ number_format($data['total_outstanding'], 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Collection Rate -->
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-100 rounded-lg p-2">
                    <heroicon-o-chart-pie class="w-5 h-5 text-purple-600" />
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Collection Rate</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $data['collection_rate'] }}%</p>
                </div>
            </div>
        </div>

        <!-- Members with Outstanding -->
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-orange-100 rounded-lg p-2">
                    <heroicon-o-users class="w-5 h-5 text-orange-600" />
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Members Outstanding</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $data['members_with_outstanding'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-xs text-gray-500 text-center">
        Summary for {{ $activeYear->name }} • Updated {{ now()->format('M d, Y H:i') }}
    </div>
@else
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center">
            <heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 mr-2" />
            <div>
                <p class="text-sm font-medium text-yellow-800">No Active Academic Year</p>
                <p class="text-xs text-yellow-600">Outstanding calculations require an active academic year.</p>
            </div>
        </div>
    </div>
@endif
