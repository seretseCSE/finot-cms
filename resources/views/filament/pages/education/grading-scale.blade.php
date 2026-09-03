<x-filament-panels::page>
    <div class="space-y-6" data-tour="grading-scale">
        <x-filament::section>
            <x-slot name="heading">Grade boundaries</x-slot>
            <x-slot name="description">
                Letter grades are assigned from each subject’s percentage (score ÷ max × 100). Rankings use total average, not year-level pass/fail.
            </x-slot>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Scale name</label>
                    <input
                        type="text"
                        wire:model="scaleName"
                        class="w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900"
                    >
                    @error('scaleName') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Letter</th>
                                <th class="px-3 py-2">Min %</th>
                                <th class="px-3 py-2">Max %</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->bands as $index => $band)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-3 py-2">
                                        <input type="text" wire:model="bands.{{ $index }}.label" class="w-24 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900">
                                        @error("bands.$index.label") <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" max="100" wire:model="bands.{{ $index }}.min_score" class="w-24 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900">
                                        @error("bands.$index.min_score") <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" max="100" wire:model="bands.{{ $index }}.max_score" class="w-24 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900">
                                        @error("bands.$index.max_score") <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <x-filament::button color="danger" size="sm" wire:click="removeBand({{ $index }})" wire:confirm="Remove this grade band?">
                                            Remove
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" wire:click="addBand">Add band</x-filament::button>
                    <x-filament::button wire:click="save">Save scale</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
