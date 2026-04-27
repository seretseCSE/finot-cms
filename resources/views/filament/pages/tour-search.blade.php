<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Search Tours</h3>
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button wire:click="searchTours" icon="heroicon-o-magnifying-glass">
                    Search Tours
                </x-filament::button>
            </div>
        </div>

        <!-- Results -->
        @if($this->hasSearched)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">
                        Search Results
                        <span class="text-sm font-normal text-gray-500">({{ $this->getTotalResultsCount() }} found)</span>
                    </h3>
                </div>

                @if($this->getTotalResultsCount() === 0)
                    <div class="text-center py-8 text-gray-500">
                        <x-filament::icon icon="heroicon-o-map" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                        <p>No tours found matching your criteria.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 border rounded-lg">
                        @foreach($this->results as $tour)
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between gap-4 flex-wrap">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-medium text-gray-900">{{ $tour['place'] }}</p>
                                            <span @class([
                                                'px-2 py-0.5 rounded text-xs font-medium',
                                                'bg-gray-100 text-gray-700' => $tour['status_color'] === 'gray',
                                                'bg-blue-100 text-blue-700' => $tour['status_color'] === 'blue',
                                                'bg-yellow-100 text-yellow-700' => $tour['status_color'] === 'yellow',
                                                'bg-green-100 text-green-700' => $tour['status_color'] === 'green',
                                                'bg-red-100 text-red-700' => $tour['status_color'] === 'red',
                                            ])>
                                                {{ $tour['status'] }}
                                            </span>
                                        </div>

                                        @if($tour['description'])
                                            <p class="text-sm text-gray-500 mt-1">{{ Str::limit($tour['description'], 120) }}</p>
                                        @endif

                                        <div class="flex items-center gap-4 mt-2 text-sm text-gray-500 flex-wrap">
                                            <span class="flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4" />
                                                {{ $tour['ethiopian_date'] ?? $tour['tour_date'] }}
                                            </span>
                                            @if($tour['start_time'])
                                                <span class="flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-clock" class="w-4 h-4" />
                                                    {{ $tour['start_time'] }}
                                                </span>
                                            @endif
                                            <span class="flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-currency-dollar" class="w-4 h-4" />
                                                {{ $tour['cost'] }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-users" class="w-4 h-4" />
                                                {{ $tour['confirmed_count'] }} / {{ $tour['max_capacity'] ?: 'Unlimited' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="text-right flex-shrink-0">
                                        @if($tour['created_by'])
                                            <p class="text-xs text-gray-500">By {{ $tour['created_by'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
