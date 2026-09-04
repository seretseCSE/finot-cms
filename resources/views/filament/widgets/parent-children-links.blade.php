<x-filament-widgets::widget>
    <div class="grid gap-4 sm:grid-cols-2" data-tour="parent-tiles">
        @foreach ($links as $link)
            <a href="{{ $link['url'] }}" class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-6 hover:border-primary-400 transition-colors">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $link['label'] }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $link['description'] }}</p>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
