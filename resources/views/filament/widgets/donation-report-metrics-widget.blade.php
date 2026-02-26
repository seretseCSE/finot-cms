<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <!-- Total Donated -->
    <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-green-100 rounded-lg p-2">
                <heroicon-o-gift class="w-5 h-5 text-green-600" />
            </div>
            <div class="ml-3">
                <p class="text-xs font-medium text-gray-600">Total Donated</p>
                <p class="text-lg font-semibold text-gray-900">Birr {{ number_format($data['totalDonated'], 2) }}</p>
            </div>
        </div>
    </div>

    <!-- Total This Year -->
    <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-blue-100 rounded-lg p-2">
                <heroicon-o-calendar-days class="w-5 h-5 text-blue-600" />
            </div>
            <div class="ml-3">
                <p class="text-xs font-medium text-gray-600">This Year</p>
                <p class="text-lg font-semibold text-gray-900">Birr {{ number_format($data['totalThisYear'], 2) }}</p>
            </div>
        </div>
    </div>

    <!-- Last Donation -->
    <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-purple-100 rounded-lg p-2">
                <heroicon-o-clock class="w-5 h-5 text-purple-600" />
            </div>
            <div class="ml-3">
                <p class="text-xs font-medium text-gray-600">Last Donation</p>
                <p class="text-sm font-semibold text-gray-900">
                    @if($data['lastDonation'])
                        {{ $data['lastDonation']->ethiopian_date }}
                    @else
                        No donations
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 text-xs text-gray-500 text-center">
    Updated {{ now()->format('M d, Y H:i') }}
</div>
