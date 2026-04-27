<x-filament-panels::page>
    <div class="space-y-10">
        {{-- Header & Configuration Section --}}
        <x-filament::section icon="heroicon-o-adjustments-horizontal" icon-color="primary">
            <x-slot name="heading">
                <span class="text-xl">Export Configuration</span>
            </x-slot>
            <x-slot name="description">
                Configure your audit log export parameters and filters.
            </x-slot>

            <form wire:submit="export" class="space-y-6">
                <div class="p-4 bg-gray-50/50 dark:bg-white/5 rounded-xl border border-dashed border-gray-300 dark:border-white/10">
                    {{ $this->form }}
                </div>
                
                <div class="flex flex-wrap items-center gap-4">
                    <x-filament::button type="submit" size="lg" icon="heroicon-m-arrow-down-tray" wire:loading.attr="disabled">
                        <x-filament::loading-indicator wire:loading wire:target="export" />
                        Export Logs
                    </x-filament::button>
                    
                    <x-filament::button type="button" color="gray" variant="outline" size="lg" icon="heroicon-m-magnifying-glass" wire:click="preview">
                        <x-filament::loading-indicator wire:loading wire:target="preview" />
                        Live Preview
                    </x-filament::button>
                    
                    <x-filament::button type="button" wire:click="resetFilters" color="danger" variant="ghost" size="sm" class="ml-auto">
                        Clear All Filters
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Main Stats Grid --}}
        <div>
            <h3 class="text-sm font-bold uppercase tracking-widest text-gray-500 mb-4 px-1">Engagement Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $stats = $this->getStatistics();
                    $cards = [
                        ['label' => 'Total Logs', 'value' => $stats['total_logs'], 'icon' => 'heroicon-o-archive-box', 'color' => 'primary', 'desc' => 'Lifetime records'],
                        ['label' => 'Last 24 Hours', 'value' => $stats['last_24h'], 'icon' => 'heroicon-o-bolt', 'color' => 'success', 'desc' => 'Real-time velocity'],
                        ['label' => 'Last 7 Days', 'value' => $stats['last_7d'], 'icon' => 'heroicon-o-calendar', 'color' => 'info', 'desc' => 'Weekly trend'],
                        ['label' => 'Last 30 Days', 'value' => $stats['last_30d'], 'icon' => 'heroicon-o-chart-bar', 'color' => 'warning', 'desc' => 'Monthly volume'],
                    ];
                @endphp

                @foreach($cards as $card)
                    <x-filament::section class="transition-all duration-200 hover:shadow-md border-t-4 border-t-{{ $card['color'] }}-500">
                        <div class="flex items-center gap-x-4">
                            <div class="p-2 bg-{{ $card['color'] }}-50 dark:bg-{{ $card['color'] }}-500/10 rounded-lg">
                                <x-filament::icon icon="{{ $card['icon'] }}" class="h-6 w-6 text-{{ $card['color'] }}-600" />
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $card['label'] }}</p>
                                <p class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($card['value']) }}</p>
                            </div>
                        </div>
                        <div class="mt-2 text-[10px] text-gray-400 font-medium italic">{{ $card['desc'] }}</div>
                    </x-filament::section>
                @endforeach
            </div>
        </div>

        {{-- Split View: Users & Actions --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Top Users Table --}}
            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-users" class="h-5 w-5 text-gray-400" />
                        <span>Most Active Users</span>
                    </div>
                </x-slot>

                <div class="mt-4 flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/5">
                                <thead>
                                    <tr>
                                        <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-gray-950 dark:text-white uppercase">User Identity</th>
                                        <th class="px-3 py-3.5 text-right text-xs font-semibold text-gray-950 dark:text-white uppercase">Log Count</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @forelse ($stats['top_users'] as $user)
                                        <tr class="group hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                                                <div class="flex items-center gap-3">
                                                    <x-filament::avatar :src="'https://ui-avatars.com/api/?background=random&name=' . urlencode($user['name'])" size="sm" />
                                                    <span class="font-medium text-gray-900 dark:text-gray-200">{{ $user['name'] }}</span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-right font-mono text-primary-600">
                                                {{ number_format($user['count']) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="py-10 text-center text-sm text-gray-400 italic">No activity data recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            {{-- Action Pills --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-finger-print" class="h-5 w-5 text-gray-400" />
                        <span>Action Heatmap</span>
                    </div>
                </x-slot>

                <div class="flex flex-wrap gap-3 mt-4">
                    @forelse ($stats['top_actions'] as $action => $count)
                        <div class="flex flex-col items-center justify-center p-3 flex-1 min-w-[100px] bg-white dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-xl shadow-sm">
                            <span class="text-xl font-black text-gray-900 dark:text-white">{{ number_format($count) }}</span>
                            <span class="text-[10px] font-bold uppercase text-primary-500 tracking-tighter">{{ $action }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 italic py-4">No actions captured.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        {{-- Footer Advice --}}
        <div class="relative overflow-hidden rounded-2xl bg-primary-600 px-6 py-8 shadow-xl sm:px-12 sm:py-10">
            <div class="relative z-10 flex flex-col md:flex-row items-center gap-6">
                <div class="p-3 bg-white/20 rounded-full backdrop-blur-md">
                    <x-filament::icon icon="heroicon-o-light-bulb" class="h-8 w-8 text-white" />
                </div>
                <div class="text-center md:text-left">
                    <h3 class="text-lg font-bold text-white">Pro-Tips for Data Export</h3>
                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-2 text-sm text-primary-100">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                            <span>Excel: Best for complex Pivot Tables.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                            <span>CSV: Native format for Python/R scripts.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                            <span>PDF: Ideal for executive summaries.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                            <span>Audit: Every export is tracked for security.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary-500 opacity-50"></div>
            <div class="absolute -left-10 -bottom-10 h-40 w-40 rounded-full bg-primary-700 opacity-50"></div>
        </div>
    </div>
</x-filament-panels::page>