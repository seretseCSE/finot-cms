<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <h2 class="text-xl font-semibold">Member Timeline</h2>

                <div>
                    <x-filament::button wire:click="searchTimeline">
                        Search Timeline / ፈልግ
                    </x-filament::button>
                </div>
            </div>

            <div class="mt-6">
                {{ $this->form }}
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <div class="flex gap-2 flex-wrap">
                    @foreach($this->getTabs() as $key => $label)
                        <button
                            type="button"
                            wire:click="setActiveTab('{{ $key }}')"
                            class="px-3 py-1 rounded text-sm border {{ $activeTab === $key ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-200' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="p-6">
                @if(!$hasSearched || !$this->hasAnyFilterApplied())
                    <p class="text-gray-500 text-center py-8">Please apply at least one filter to view timeline</p>
                @else
                    @if(count($events) === 0)
                        <p class="text-gray-500 text-center py-8">No timeline events found</p>
                    @else
                        <div class="space-y-4">
                            @foreach($events as $event)
                                @php
                                    $badgeColor = 'bg-gray-100 text-gray-800';
                                    $dotColor = 'bg-gray-400';

                                    if (($event['event_type'] ?? '') === 'group_join') { $badgeColor = 'bg-green-100 text-green-800'; $dotColor = 'bg-green-500'; }
                                    if (($event['event_type'] ?? '') === 'group_removed') { $badgeColor = 'bg-orange-100 text-orange-800'; $dotColor = 'bg-orange-500'; }
                                    if (\Illuminate\Support\Str::startsWith(($event['event_type'] ?? ''), 'education_')) { $badgeColor = 'bg-blue-100 text-blue-800'; $dotColor = 'bg-blue-500'; }
                                    if (($event['event_group'] ?? '') === 'attendance') { $badgeColor = 'bg-purple-100 text-purple-800'; $dotColor = 'bg-purple-500'; }
                                    if (($event['event_group'] ?? '') === 'contributions') { $badgeColor = 'bg-green-100 text-green-800'; $dotColor = 'bg-green-500'; }
                                @endphp

                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 w-3 h-3 rounded-full mt-2 {{ $dotColor }}"></div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="px-2 py-0.5 rounded text-xs {{ $badgeColor }}">
                                                    {{ $event['event_group'] ?? 'Event' }}
                                                </span>
                                                <span class="text-sm font-medium text-gray-900">{{ $event['member_name'] ?? '' }}</span>
                                                <span class="text-xs text-gray-500">{{ $event['member_code'] ?? '' }}</span>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $this->formatEthiopianDate($event['event_date'] ?? null) }}
                                            </div>
                                        </div>

                                        <div class="mt-1 text-gray-700">
                                            {{ $event['description'] ?? '' }}
                                        </div>

                                        @if(!empty($event['performed_by']))
                                            <div class="mt-1 text-xs text-gray-500">
                                                Performed By: {{ $event['performed_by'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($hasMore)
                            <div class="mt-6 flex justify-center">
                                <x-filament::button color="gray" wire:click="loadMore">
                                    Load More
                                </x-filament::button>
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
