<x-filament-panels::page>
    <div class="space-y-8" data-tour="student-attendance">
        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Class attendance') }}</h3>
            @forelse ($this->classRecords() as $record)
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 flex items-center justify-between gap-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $record->session?->session_date?->toDateString() ?? '—' }}
                        · {{ $record->session?->class?->name ?? __('Class session') }}
                    </span>
                    <span class="text-sm font-medium">{{ $record->status }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No class attendance records yet.') }}</p>
            @endforelse
        </section>

        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Event attendance') }}</h3>
            @forelse ($this->eventRecords() as $record)
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 flex items-center justify-between gap-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $record->event_date?->toDateString() }} · {{ $record->event_type }}
                    </span>
                    <span class="text-sm font-medium">{{ $record->status }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No event attendance records yet.') }}</p>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
