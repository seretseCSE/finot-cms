<x-filament-panels::page>
    <div class="space-y-6" data-tour="roster-report">
        <x-filament::section>
            <x-slot name="heading">Filters</x-slot>
            {{ $this->form }}
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button wire:click="generate">Show roster</x-filament::button>
                <x-filament::button wire:click="compute" color="gray">Compute results</x-filament::button>
            </div>
        </x-filament::section>

        @if ($this->report)
            @if ($this->report['needs_compute'] ?? false)
                <x-filament::section>
                    <p class="text-sm text-gray-600 dark:text-gray-300">No computed snapshot yet. Click <strong>Compute results</strong> to generate totals, averages, and ranks.</p>
                </x-filament::section>
            @else
                <x-filament::section>
                    <x-slot name="heading">Roster</x-slot>
                    <div class="mb-4">
                        @include('filament.pages.education.partials.report-downloads')
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-3">Student</th>
                                    @foreach ($this->report['subjects'] as $subject)
                                        <th class="py-2 pr-3">{{ $subject['name'] }}</th>
                                    @endforeach
                                    <th class="py-2 pr-3">Total</th>
                                    <th class="py-2 pr-3">Avg</th>
                                    <th class="py-2 pr-3">Rank</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->report['rows'] as $row)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2 pr-3">
                                            <div class="font-medium">{{ $row['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['code'] }}</div>
                                        </td>
                                        @foreach ($this->report['subjects'] as $subject)
                                            @php $cell = $row['subjects'][$subject['id']] ?? null; @endphp
                                            <td class="py-2 pr-3">
                                                {{ $cell['total'] ?? '—' }}
                                                @if (!empty($cell['transferred']))
                                                    <span class="text-xs text-amber-600">T</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="py-2 pr-3">{{ $row['total'] }}</td>
                                        <td class="py-2 pr-3">{{ $row['average'] }}</td>
                                        <td class="py-2 pr-3">{{ $row['rank'] }}{{ $row['rank_of'] ? '/'.$row['rank_of'] : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
