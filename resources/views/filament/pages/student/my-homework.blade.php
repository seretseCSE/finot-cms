<x-filament-panels::page>
    <div class="space-y-4" data-tour="my-homework">
        @forelse($this->items() as $item)
            <article class="rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $item->title }}</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $item->class?->name }}
                            @if($item->subject) · {{ $item->subject->name }} @endif
                            @if($item->due_at) · {{ __('Due') }}: {{ $item->due_at->format('M j, Y') }} @endif
                        </p>
                    </div>
                    @if($item->fileUrl())
                        <a href="{{ $item->fileUrl() }}" target="_blank" rel="noopener"
                           class="inline-flex items-center rounded-lg bg-primary-500/10 px-3 py-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400">
                            {{ __('Download') }}
                        </a>
                    @endif
                </div>
                @if($item->instructions)
                    <p class="mt-3 text-sm leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $item->instructions }}</p>
                @endif
            </article>
        @empty
            @include('filament.pages.student.partials.empty-state', [
                'icon' => 'heroicon-o-clipboard-document-list',
                'title' => __('No homework yet'),
                'description' => __('When your teacher publishes an assignment for your class, you can open it and download the file here.'),
            ])
        @endforelse
    </div>
</x-filament-panels::page>
