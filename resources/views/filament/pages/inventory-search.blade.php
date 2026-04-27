<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Search Inventory</h3>
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button wire:click="searchInventory" icon="heroicon-o-magnifying-glass">
                    Search Inventory
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
                        <x-filament::icon icon="heroicon-o-archive-box-x-mark" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                        <p>No inventory items found matching your criteria.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 border rounded-lg">
                        @foreach($this->results as $item)
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between gap-4 flex-wrap">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-medium text-gray-900">{{ $item['name'] }}</p>
                                            <span class="text-xs text-gray-500">{{ $item['item_code'] }}</span>
                                            <span @class([
                                                'px-2 py-0.5 rounded text-xs font-medium',
                                                'bg-green-100 text-green-700' => $item['status_color'] === 'green',
                                                'bg-orange-100 text-orange-700' => $item['status_color'] === 'orange',
                                                'bg-red-100 text-red-700' => $item['status_color'] === 'red',
                                                'bg-gray-100 text-gray-700' => $item['status_color'] === 'gray',
                                            ])>
                                                {{ $item['status'] }}
                                            </span>
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                                {{ $item['category'] }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-4 mt-2 text-sm text-gray-500 flex-wrap">
                                            <span class="flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-cube" class="w-4 h-4" />
                                                Stock: <strong @class(['text-gray-900', 'text-red-600' => $item['current_stock'] < 5])>{{ number_format($item['current_stock'], 2) }}</strong> {{ $item['unit'] }}
                                            </span>
                                            @if($item['location'])
                                                <span class="flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-map-pin" class="w-4 h-4" />
                                                    {{ $item['location'] }}
                                                </span>
                                            @endif
                                            @if($item['supplier'])
                                                <span class="flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-truck" class="w-4 h-4" />
                                                    {{ $item['supplier'] }}
                                                </span>
                                            @endif
                                            @if($item['purchase_price'])
                                                <span class="flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-currency-dollar" class="w-4 h-4" />
                                                    {{ number_format($item['purchase_price'], 2) }} ETB
                                                </span>
                                            @endif
                                            @if($item['ethiopian_purchase_date'])
                                                <span class="flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4" />
                                                    {{ $item['ethiopian_purchase_date'] }}
                                                </span>
                                            @endif
                                        </div>
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
