<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $options = $this->filterOptions();
    @endphp

    <div class="space-y-6" data-tour="student-results">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5 sm:p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Filter results') }}</p>
                <button type="button" wire:click="clearFilters" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    {{ __('Show all') }}
                </button>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-500">{{ __('Academic year') }}</span>
                    <select wire:model.live="academic_year_id" class="w-full rounded-lg border-gray-200 bg-white text-sm dark:border-white/10 dark:bg-gray-900">
                        <option value="">{{ __('All years') }}</option>
                        @foreach ($options['academic_years'] as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-500">{{ __('Semester') }}</span>
                    <select wire:model.live="term_id" class="w-full rounded-lg border-gray-200 bg-white text-sm dark:border-white/10 dark:bg-gray-900">
                        <option value="">{{ __('All semesters') }}</option>
                        @foreach ($options['terms'] as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-500">{{ __('Batch') }}</span>
                    <select wire:model.live="batch_id" class="w-full rounded-lg border-gray-200 bg-white text-sm dark:border-white/10 dark:bg-gray-900">
                        <option value="">{{ __('All batches') }}</option>
                        @foreach ($options['batches'] as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-500">{{ __('Subject') }}</span>
                    <select wire:model.live="subject_id" class="w-full rounded-lg border-gray-200 bg-white text-sm dark:border-white/10 dark:bg-gray-900">
                        <option value="">{{ __('All subjects') }}</option>
                        @foreach ($options['subjects'] as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Semester average') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $summary['semester_average'] !== null ? number_format($summary['semester_average'], 1).'%' : '—' }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Year average') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $summary['year_average'] !== null ? number_format($summary['year_average'], 1).'%' : '—' }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Class rank') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                    @if ($summary['overall_rank'])
                        {{ $summary['overall_rank'] }}
                        <span class="text-base font-normal text-gray-500">/ {{ $summary['cohort_size'] }}</span>
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Subject results') }}</h3>
                <p class="mt-0.5 text-xs text-gray-500">{{ __('Open a subject to see each assessment score.') }}</p>
            </div>

            @if (empty($summary['items']))
                <div class="p-4">
                    @include('filament.pages.student.partials.empty-state', [
                        'icon' => 'heroicon-o-academic-cap',
                        'title' => __('No results for these filters'),
                        'description' => __('Try another year, semester, batch, or subject — or ask your teacher if scores are not saved yet.'),
                    ])
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($summary['items'] as $row)
                        <details class="group">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                                <div class="min-w-[10rem] flex-1">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $row['subject'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ $row['term'] ?? '—' }}
                                        @if (! empty($row['academic_year'])) · {{ $row['academic_year'] }} @endif
                                        @if (! empty($row['batch'])) · {{ $row['batch'] }} @endif
                                        @if (! empty($row['class'])) · {{ $row['class'] }} @endif
                                        @if (! empty($row['transferred']))
                                            <span class="text-amber-600">· {{ __('transferred') }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="text-sm font-medium text-gray-950 dark:text-white">
                                    @if ($row['percent'] !== null)
                                        {{ number_format($row['percent'], 1) }}%
                                    @elseif ($row['score'] !== null)
                                        {{ rtrim(rtrim(number_format($row['score'], 2), '0'), '.') }} / {{ $row['max_score'] }}
                                    @else
                                        —
                                    @endif
                                </div>
                                <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                    {{ $row['letter'] ?? '—' }}
                                </span>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    @if ($row['rank'])
                                        {{ __('Rank') }} {{ $row['rank'] }}
                                        @if ($row['peers'])
                                            <span class="text-xs text-gray-500">/ {{ $row['peers'] }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </div>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 text-gray-400 transition group-open:rotate-180" />
                            </summary>

                            <div class="border-t border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                                @if (! empty($row['assessments']))
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Assessments') }}</p>
                                    <div class="overflow-x-auto">
                                        <div class="min-w-[480px]">
                                            <div class="grid grid-cols-4 gap-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                                <div>{{ __('Assessment') }}</div>
                                                <div>{{ __('Score') }}</div>
                                                <div>{{ __('Weight') }}</div>
                                                <div>{{ __('Status') }}</div>
                                            </div>
                                            @foreach ($row['assessments'] as $assessment)
                                                <div class="mt-2 grid grid-cols-4 gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <div class="font-medium">{{ $assessment['name'] }}</div>
                                                    <div>
                                                        @if ($assessment['is_absent'])
                                                            —
                                                        @elseif ($assessment['score'] !== null)
                                                            {{ rtrim(rtrim(number_format((float) $assessment['score'], 2), '0'), '.') }}
                                                            / {{ $assessment['max_score'] }}
                                                        @else
                                                            —
                                                        @endif
                                                    </div>
                                                    <div>{{ $assessment['weight'] }}%</div>
                                                    <div>
                                                        @if ($assessment['is_absent'])
                                                            <span class="text-amber-600">{{ __('Absent') }}</span>
                                                        @elseif ($assessment['score'] === null)
                                                            <span class="text-gray-400">{{ __('Pending') }}</span>
                                                        @else
                                                            <span class="text-green-600">{{ __('Recorded') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500">{{ __('No assessment breakdown for this subject. The total above is the saved subject score.') }}</p>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>

        @if (! empty($summary['term_results']))
            <div class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Computed semester snapshots') }}</h3>
                @foreach ($summary['term_results'] as $termResult)
                    <div class="border-t border-gray-100 pt-3 first:border-0 first:pt-0 dark:border-white/10">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium text-gray-950 dark:text-white">
                                {{ $termResult['term'] ?? __('Semester') }}
                                @if (! empty($termResult['batch']))
                                    <span class="text-sm font-normal text-gray-500">· {{ $termResult['batch'] }}</span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ __('Avg') }} {{ $termResult['average'] !== null ? number_format($termResult['average'], 1).'%' : '—' }}
                                @if ($termResult['rank'])
                                    · {{ __('Rank') }} {{ $termResult['rank'] }}{{ $termResult['rank_of'] ? '/'.$termResult['rank_of'] : '' }}
                                @endif
                            </p>
                        </div>
                        @if (! empty($termResult['breakdown']))
                            <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                @foreach ($termResult['breakdown'] as $b)
                                    <li>
                                        {{ $b['subject_name'] ?? __('Subject') }}:
                                        {{ isset($b['total']) ? number_format($b['total'], 1).'%' : '—' }}
                                        @if (! empty($b['rank']))
                                            ({{ __('rank') }} {{ $b['rank'] }}{{ ! empty($b['rank_of']) ? '/'.$b['rank_of'] : '' }})
                                        @endif
                                        @if (! empty($b['transferred']))
                                            <span class="text-amber-600">{{ __('transferred') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
