<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-tour="my-children">
        @forelse($this->childrenCards() as $child)
            <article class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $child['name'] }}</h3>
                <p class="text-xs text-gray-500 mt-1">{{ $child['class'] }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">{{ __('Semester avg') }}</dt>
                        <dd class="font-medium">{{ $child['average'] !== null ? number_format($child['average'], 1).'%' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">{{ __('Rank') }}</dt>
                        <dd class="font-medium">
                            @if($child['rank'])
                                {{ $child['rank'] }} / {{ $child['cohort'] }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">{{ __('This week attendance') }}</dt>
                        <dd class="font-medium">{{ $child['attendance_rate'] !== null ? $child['attendance_rate'].'%' : '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <a href="{{ \App\Filament\Pages\Student\ClassAnnouncements::getUrl() }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('Announcements') }}</a>
                    <a href="{{ \App\Filament\Pages\Student\MyHomework::getUrl() }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('Homework') }}</a>
                    <a href="{{ \App\Filament\Pages\Student\ClassMaterials::getUrl() }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('Materials') }}</a>
                </div>
            </article>
        @empty
            <p class="text-sm text-gray-500 col-span-full">{{ __('No linked children yet. Ask the church office to link your kids to your phone.') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
