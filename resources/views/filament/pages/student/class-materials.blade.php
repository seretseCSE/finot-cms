<x-filament-panels::page>
    <div class="space-y-4" data-tour="class-materials">
        @forelse($this->items() as $item)
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $item->title }}</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $item->class?->name }}
                        @if($item->subject) · {{ $item->subject->name }} @endif
                    </p>
                    @if($item->description)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $item->description }}</p>
                    @endif
                </div>
                @if($item->fileUrl())
                    <a href="{{ $item->fileUrl() }}" target="_blank" rel="noopener"
                       class="inline-flex items-center rounded-lg bg-primary-500/10 px-3 py-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400">
                        {{ __('Download') }}
                    </a>
                @endif
            </article>
        @empty
            @include('filament.pages.student.partials.empty-state', [
                'icon' => 'heroicon-o-folder-open',
                'title' => __('No class materials yet'),
                'description' => __('Files shared specifically with your class will show up here.'),
            ])
        @endforelse
    </div>
</x-filament-panels::page>
