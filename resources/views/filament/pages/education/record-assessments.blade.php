<x-filament-panels::page>
    <div class="space-y-4" data-tour="record-assessments">
        <x-filament::section>
            <x-slot name="heading">Select assessment</x-slot>
            <form wire:submit="loadRoster" class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-sm font-medium">Active semester</label>
                    <select wire:model.live="termId" class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        <option value="">Select</option>
                        @foreach ($terms as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Offering</label>
                    <select wire:model.live="offeringId" class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        <option value="">All / select</option>
                        @foreach ($offerings as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Assessment</label>
                    <select wire:model="assessmentId" class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        <option value="">Select</option>
                        @foreach ($assessments as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('assessmentId') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <x-filament::button type="submit">Load roster</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if (count($this->rows))
            <x-filament::section>
                <x-slot name="heading">Scores</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2">Student</th>
                                <th class="py-2">Score</th>
                                <th class="py-2">Absent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $i => $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-2">
                                        <div class="font-medium">{{ $row['name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['code'] }}</div>
                                    </td>
                                    <td class="py-2">
                                        <input type="number" step="0.01" wire:model="rows.{{ $i }}.score" class="fi-input w-28 rounded-lg border-gray-300 dark:bg-gray-900" />
                                    </td>
                                    <td class="py-2">
                                        <input type="checkbox" wire:model="rows.{{ $i }}.is_absent" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <x-filament::button wire:click="save">Save scores</x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
