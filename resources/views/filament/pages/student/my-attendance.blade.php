<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2" data-tour="student-attendance">
        <section class="rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-academic-cap" class="h-5 w-5" />
                </div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Class attendance') }}</h3>
            </div>

            @forelse ($this->classRecords() as $record)
                <div class="flex items-center justify-between gap-4 border-t border-gray-100 py-3 first:border-0 first:pt-0 dark:border-white/10">
                    <div>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $record->session?->class?->name ?? __('Class session') }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $record->session?->session_date?->toDateString() ?? '—' }}</p>
                    </div>
                    <span @class([
                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300' => $record->status === 'Present',
                        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' => $record->status === 'Absent',
                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => in_array($record->status, ['Late', 'Excused'], true),
                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! in_array($record->status, ['Present', 'Absent', 'Late', 'Excused'], true),
                    ])>{{ $record->status }}</span>
                </div>
            @empty
                @include('filament.pages.student.partials.empty-state', [
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'title' => __('No class attendance yet'),
                    'description' => __('When your teacher locks a session, it will show up here.'),
                ])
            @endforelse
        </section>

        <section class="rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" />
                </div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Event attendance') }}</h3>
            </div>

            @forelse ($this->eventRecords() as $record)
                <div class="flex items-center justify-between gap-4 border-t border-gray-100 py-3 first:border-0 first:pt-0 dark:border-white/10">
                    <div>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $record->event_type }}</p>
                        <p class="text-xs text-gray-500">{{ $record->event_date?->toDateString() ?? '—' }}</p>
                    </div>
                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">
                        {{ $record->status }}
                    </span>
                </div>
            @empty
                @include('filament.pages.student.partials.empty-state', [
                    'icon' => 'heroicon-o-calendar-days',
                    'title' => __('No event attendance yet'),
                    'description' => __('Church or school event attendance will appear here.'),
                ])
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
