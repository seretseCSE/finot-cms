<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Onboarding Progress
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $completedTours }} of {{ $totalTours }} tours completed
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $overallProgress }}%
                    </div>
                </div>
            </div>

            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-primary-500 dark:bg-primary-400 rounded-full transition-all duration-500 ease-out"
                     style="width: {{ $overallProgress }}%"></div>
            </div>

            @if(count($stats) > 0)
                <div class="space-y-2 mt-4">
                    @foreach($stats as $stat)
                        <div class="flex items-center justify-between text-sm py-1.5 px-3 rounded-lg
                                    @if($stat['status'] === 'completed') bg-success-50 dark:bg-success-900/20
                                    @elseif($stat['status'] === 'skipped') bg-gray-50 dark:bg-gray-800
                                    @elseif($stat['status'] === 'in_progress') bg-warning-50 dark:bg-warning-900/20
                                    @else bg-gray-50 dark:bg-gray-800
                                    @endif">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($stat['status'] === 'completed')
                                    <x-filament::icon name="heroicon-o-check-circle" class="w-4 h-4 text-success-500 shrink-0" />
                                @elseif($stat['status'] === 'skipped')
                                    <x-filament::icon name="heroicon-o-forward" class="w-4 h-4 text-gray-400 shrink-0" />
                                @elseif($stat['status'] === 'in_progress')
                                    <x-filament::icon name="heroicon-o-play" class="w-4 h-4 text-warning-500 shrink-0" />
                                @else
                                    <x-filament::icon name="heroicon-o-ellipsis-horizontal-circle" class="w-4 h-4 text-gray-400 shrink-0" />
                                @endif
                                <span class="truncate font-medium">{{ $stat['label'] }}</span>
                            </div>
                            <button
                                type="button"
                                data-restart-tour="{{ $stat['key'] }}"
                                class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 ml-2 shrink-0"
                            >
                                {{ $stat['status'] === 'completed' ? 'Replay' : ($stat['status'] === 'in_progress' ? 'Continue' : 'Start') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">
                    No tours available for your role.
                </p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
