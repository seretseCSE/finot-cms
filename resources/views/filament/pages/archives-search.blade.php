<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Search Archives</h3>
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button wire:click="searchArchives" icon="heroicon-o-magnifying-glass">
                    Search Archives
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
                        <p>No archived records found matching your criteria.</p>
                    </div>
                @else
                    <div class="space-y-6">
                        @if(!empty($this->results['contributions']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <x-filament::icon icon="heroicon-o-banknotes" class="w-4 h-4 mr-2" />
                                    Contributions ({{ count($this->results['contributions']) }})
                                </h4>
                                <div class="divide-y divide-gray-100 border rounded-lg">
                                    @foreach($this->results['contributions'] as $item)
                                        <div class="p-4 flex items-center justify-between hover:bg-gray-50">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $item['title'] }}</p>
                                                <p class="text-sm text-gray-500">{{ $item['subtitle'] }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-medium text-gray-900">{{ number_format($item['amount'], 2) }} ETB</p>
                                                <p class="text-xs text-gray-500">{{ $item['date'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($this->results['blog_posts']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <x-filament::icon icon="heroicon-o-document-text" class="w-4 h-4 mr-2" />
                                    Blog Posts ({{ count($this->results['blog_posts']) }})
                                </h4>
                                <div class="divide-y divide-gray-100 border rounded-lg">
                                    @foreach($this->results['blog_posts'] as $item)
                                        <div class="p-4 hover:bg-gray-50">
                                            <p class="font-medium text-gray-900">{{ $item['title'] }}</p>
                                            <p class="text-sm text-gray-500">{{ $item['subtitle'] }} — {{ $item['date'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($this->results['announcements']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <x-filament::icon icon="heroicon-o-megaphone" class="w-4 h-4 mr-2" />
                                    Announcements ({{ count($this->results['announcements']) }})
                                </h4>
                                <div class="divide-y divide-gray-100 border rounded-lg">
                                    @foreach($this->results['announcements'] as $item)
                                        <div class="p-4 hover:bg-gray-50">
                                            <p class="font-medium text-gray-900">{{ $item['title'] }}</p>
                                            <p class="text-sm text-gray-500">{{ $item['subtitle'] }} — {{ $item['date'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($this->results['media_items']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <x-filament::icon icon="heroicon-o-photo" class="w-4 h-4 mr-2" />
                                    Media Items ({{ count($this->results['media_items']) }})
                                </h4>
                                <div class="divide-y divide-gray-100 border rounded-lg">
                                    @foreach($this->results['media_items'] as $item)
                                        <div class="p-4 hover:bg-gray-50">
                                            <p class="font-medium text-gray-900">{{ $item['title'] }}</p>
                                            <p class="text-sm text-gray-500">{{ $item['subtitle'] }} — {{ $item['date'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
