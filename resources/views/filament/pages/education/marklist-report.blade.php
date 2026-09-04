<x-filament-panels::page>
    <div class="space-y-6" data-tour="marklist-report">
        <x-filament::section>
            <x-slot name="heading">Filters</x-slot>
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button wire:click="generate">Show marklist</x-filament::button>
            </div>
        </x-filament::section>

        @if ($this->report)
            <x-filament::section>
                <x-slot name="heading">Active-term marklist</x-slot>
                <div class="mb-4">
                    @include('filament.pages.education.partials.report-downloads')
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-3">Student</th>
                                @foreach ($this->report['offerings'] as $offering)
                                    @foreach ($offering['assessments'] as $assessment)
                                        <th class="py-2 pr-3">{{ $offering['subject'] }} · {{ $assessment['name'] }}</th>
                                    @endforeach
                                    <th class="py-2 pr-3">{{ $offering['subject'] }} total</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->report['rows'] as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3">
                                        <div class="font-medium">{{ $row['name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['code'] }}</div>
                                    </td>
                                    @foreach ($this->report['offerings'] as $offering)
                                        @foreach ($offering['assessments'] as $assessment)
                                            <td class="py-2 pr-3">{{ $row['cells']['a_'.$assessment['id']] ?? '—' }}</td>
                                        @endforeach
                                        <td class="py-2 pr-3 font-medium">{{ $row['cells']['s_'.$offering['subject_id']] ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
