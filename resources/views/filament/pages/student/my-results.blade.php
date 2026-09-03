<x-filament-panels::page>
    @php $summary = $this->summary(); @endphp

    <div class="space-y-4" data-tour="student-results">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:0.75rem;">
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Semester average</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $summary['semester_average'] !== null ? number_format($summary['semester_average'], 1).'%' : '—' }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Year average</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $summary['year_average'] !== null ? number_format($summary['year_average'], 1).'%' : '—' }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Class rank</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    @if ($summary['overall_rank'])
                        {{ $summary['overall_rank'] }}
                        <span class="text-sm font-normal text-gray-500">/ {{ $summary['cohort_size'] }}</span>
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-white/5 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">Grade</th>
                        <th class="px-4 py-3">Rank</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary['items'] as $row)
                        <tr class="border-t border-gray-100 dark:border-white/10">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $row['subject'] }}</span>
                                @if ($row['class'])
                                    <span class="block text-xs text-gray-500">{{ $row['class'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $row['term'] ?? '—' }}
                                @if ($row['academic_year'])
                                    <span class="block text-xs text-gray-400">{{ $row['academic_year'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium">
                                @if ($row['score'] !== null)
                                    {{ rtrim(rtrim(number_format($row['score'], 2), '0'), '.') }} / {{ $row['max_score'] }}
                                    <span class="block text-xs text-gray-500">{{ number_format($row['percent'], 1) }}%</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-primary-50 dark:bg-primary-500/10 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:text-primary-300">
                                    {{ $row['letter'] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['rank'])
                                    {{ $row['rank'] }}
                                    @if ($row['peers'])
                                        <span class="text-xs text-gray-500">/ {{ $row['peers'] }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                {{ __('No approved results yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
