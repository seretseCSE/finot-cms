<x-filament-panels::page>
    <div class="space-y-6" data-tour="promotion-board">
        <x-filament::section>
            <x-slot name="heading">Class selection</x-slot>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                <strong>Pass</strong> keeps the student in this batch and moves them to the next class.
                <strong>Fail</strong> leaves this batch for another batch at the same year level (passed subjects stay as credits).
            </p>
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button wire:click="loadBoard">Load class</x-filament::button>
            </div>
        </x-filament::section>

        @if ($this->board)
            <x-filament::section>
                <x-slot name="heading">Students</x-slot>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Pass mark: {{ $this->board['pass_mark'] }}% (from computed term averages).
                    @if (empty($this->board['rows']))
                        No students to promote.
                    @endif
                </p>

                @if (! empty($this->board['rows']))
                    <div class="mb-4 flex flex-wrap gap-2">
                        <x-filament::button wire:click="acceptSuggestions" color="gray" size="sm">
                            Accept suggestions
                        </x-filament::button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-3">Student</th>
                                    <th class="py-2 pr-3">Average</th>
                                    <th class="py-2 pr-3">Suggested</th>
                                    <th class="py-2 pr-3">Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->board['rows'] as $row)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2 pr-3">
                                            <div class="font-medium">{{ $row['name'] }}</div>
                                            @if (! empty($row['code']))
                                                <div class="text-xs text-gray-500">{{ $row['code'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3">
                                            {{ $row['average'] !== null ? number_format($row['average'], 1) : '—' }}
                                        </td>
                                        <td class="py-2 pr-3">
                                            @if ($row['suggestion'] === 'pass')
                                                <span class="text-green-600 dark:text-green-400">Pass</span>
                                            @elseif ($row['suggestion'] === 'fail')
                                                <span class="text-amber-600 dark:text-amber-400">Fail</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3">
                                            <select
                                                wire:model.live="decisions.{{ $row['enrollment_id'] }}"
                                                class="fi-input block w-full min-w-[8rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                            >
                                                <option value="">— Skip —</option>
                                                <option value="pass">Pass</option>
                                                <option value="fail">Fail (leave batch)</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            @if (! empty($this->board['rows']))
                <x-filament::section>
                    <x-slot name="heading">Destinations</x-slot>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Next class (passers)
                            </label>
                            <select
                                wire:model="pass_target_class_id"
                                class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                            >
                                @foreach ($this->board['next_class_options'] as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($this->hasFailDecisions())
                            <div class="space-y-3 md:col-span-2 md:grid md:grid-cols-2 md:gap-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Other batch (failers, same year level)
                                    </label>
                                    <select
                                        wire:model="fail_target_batch_year_id"
                                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    >
                                        <option value="">— Select batch year —</option>
                                        @foreach ($this->failBatchYearOptions() as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Class for failers
                                    </label>
                                    <select
                                        wire:model="fail_target_class_id"
                                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                    >
                                        <option value="">— Select class —</option>
                                        @foreach ($this->failClassOptions() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6">
                        <x-filament::button
                            wire:click="applyAll"
                            color="success"
                            wire:confirm="Apply Pass/Fail for all decided students? This cannot be undone in bulk (use Undo on Enrollments for individual fixes)."
                        >
                            Apply promotions
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
