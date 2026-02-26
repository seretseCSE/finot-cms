<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Total Expected -->
    <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-blue-100 rounded-lg p-2">
                <heroicon-o-calculator class="w-5 h-5 text-blue-600" />
            </div>
            <div class="ml-3">
                <p class="text-xs font-medium text-gray-600">Total Expected</p>
                <p class="text-lg font-semibold text-gray-900">Birr {{ number_format($data['totalExpected'], 2) }}</p>
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
                <p class="text-lg font-semibold text-gray-900">Birr {{ number_format($data['totalCollected'], 2) }}</p>
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
                <p class="text-lg font-semibold text-red-600">Birr {{ number_format($data['totalOutstanding'], 2) }}</p>
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
                <p class="text-lg font-semibold text-gray-900">{{ $data['collectionRate'] }}%</p>
            </div>
        </div>
    </div>

    <!-- Top Contributors -->
    <div class="bg-white rounded-lg shadow p-4 border border-gray-200 lg:col-span-4">
        <div class="flex items-center mb-3">
            <div class="flex-shrink-0 bg-orange-100 rounded-lg p-2">
                <heroicon-o-trophy class="w-5 h-5 text-orange-600" />
            </div>
            <div class="ml-3">
                <p class="text-xs font-medium text-gray-600">Top Contributors</p>
            </div>
        </div>
        
        @if(!empty($data['topContributors']))
            <div class="space-y-2">
                @foreach($data['topContributors'] as $index => $contributor)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-700">{{ $index + 1 }}. {{ $contributor['member']->full_name }}</span>
                        <span class="font-semibold text-green-600">Birr {{ number_format($contributor['total'], 2) }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-sm text-gray-500">No contributors found</div>
        @endif
    </div>
</div>

<div class="mt-4 text-xs text-gray-500 text-center">
    Updated {{ now()->format('M d, Y H:i') }}
</div>
