<x-filament-panels::page>
    <div class="space-y-4" data-tour="class-announcements">
        @forelse($this->items() as $item)
            <article class="rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $item->title }}</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $item->class?->name }}
                            @if($item->event_at)
                                · {{ __('Event') }}: {{ $item->event_at->format('M j, Y g:i A') }}
                            @endif
                        </p>
                    </div>
                    <span class="text-xs text-gray-500">{{ $item->published_at?->diffForHumans() }}</span>
                </div>
                <div class="mt-3 text-sm leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $item->body }}</div>
            </article>
        @empty
            @include('filament.pages.student.partials.empty-state', [
                'icon' => 'heroicon-o-megaphone',
                'title' => __('No class announcements yet'),
                'description' => __('Exam dates and class reminders from your teachers will appear here — not church-wide site notices.'),
            ])
        @endforelse
    </div>
</x-filament-panels::page>
