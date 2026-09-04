@props([
    'icon' => 'heroicon-o-inbox',
    'title' => '',
    'description' => '',
])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 dark:border-white/15 bg-white dark:bg-white/5 px-6 py-14 text-center') }}>
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400">
        <x-filament::icon :icon="$icon" class="h-7 w-7" />
    </div>
    <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $title }}</p>
    @if ($description)
        <p class="mt-1.5 max-w-md text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif
</div>
