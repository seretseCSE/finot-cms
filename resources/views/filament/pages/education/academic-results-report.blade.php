<x-filament-panels::page>
    <div class="space-y-6" data-tour="academic-results">
        <x-filament::section>
            <x-slot name="heading">Filters</x-slot>
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button wire:click="generateReport">Generate report</x-filament::button>
            </div>
        </x-filament::section>

        @if ($this->reportData)
            @php $data = $this->reportData; @endphp

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem;">
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Students scored</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['students'] }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Subjects</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['subjects'] }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Approved marklists</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['marklists'] }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Institution average</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['average'] !== null ? number_format($data['average'], 1).'%' : '—' }}</p>
                </x-filament::section>
            </div>

            <x-filament::section>
                <x-slot name="heading">Letter grade distribution</x-slot>
                <div class="flex flex-wrap gap-2">
                    @forelse ($data['grade_distribution'] as $label => $count)
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 dark:bg-white/10 px-3 py-1 text-sm">
                            <strong>{{ $label }}</strong> {{ $count }}
                        </span>
                    @empty
                        <p class="text-sm text-gray-500">No approved scores in this filter.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">By year / class</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2">Class</th>
                                <th class="py-2">Students</th>
                                <th class="py-2">Average %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['by_class'] as $row)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="py-2 font-medium">{{ $row['class_name'] }}</td>
                                    <td class="py-2">{{ $row['students'] }}</td>
                                    <td class="py-2">{{ number_format($row['average'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-3 text-gray-500">No class averages yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">By subject</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2">Subject</th>
                                <th class="py-2">Students</th>
                                <th class="py-2">Average %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['by_subject'] as $row)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="py-2 font-medium">{{ $row['subject_name'] }}</td>
                                    <td class="py-2">{{ $row['students'] }}</td>
                                    <td class="py-2">{{ number_format($row['average'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-3 text-gray-500">No subject averages yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
