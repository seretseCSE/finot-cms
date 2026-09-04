<x-filament-widgets::widget>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-tour="student-tiles">
        @foreach ($links as $link)
            <a href="{{ $link['url'] }}"
               class="group flex gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-primary-400 hover:shadow-sm dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-400"
               data-tour="{{ $link['tour'] }}">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                        {{ $link['label'] }}
                    </h3>
                    <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $link['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
