<x-filament-panels::page>
    <div class="space-y-6">
        {{-- View Mode Toggle --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <x-filament::button
                    wire:click="$set('viewMode', 'list')"
                    :color="$viewMode === 'list' ? 'primary' : 'gray'"
                    size="sm"
                >
                    <x-filament::icon icon="heroicon-o-list-bullet" class="w-4 h-4 mr-2" />
                    List View
                </x-filament::button>
                <x-filament::button
                    wire:click="$set('viewMode', 'matrix')"
                    :color="$viewMode === 'matrix' ? 'primary' : 'gray'"
                    size="sm"
                >
                    <x-filament::icon icon="heroicon-o-table-cells" class="w-4 h-4 mr-2" />
                    Matrix View
                </x-filament::button>
            </div>
        </div>

        {{-- Matrix View --}}
        @if($viewMode === 'matrix')
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center space-x-2">
                            <x-filament::icon
                                icon="heroicon-m-table-cells"
                                class="h-5 w-5 text-gray-500"
                            />
                            <span class="text-lg font-bold">Monthly Contribution Matrix (Ethiopian Calendar)</span>
                        </div>

                        <div class="flex items-center space-x-3">
                            {{-- Academic Year Selector --}}
                            <x-filament::select
                                wire:model.live="selectedAcademicYear"
                                placeholder="Select Academic Year"
                                class="w-48"
                            >
                                @foreach($academicYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </x-filament::select>

                            {{-- Matrix Actions --}}
                            @if($selectedAcademicYear)
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-m-sparkles"
                                    wire:click="fillAllGroups"
                                    tooltip="Fill all months with default values"
                                >
                                    Auto-Fill All
                                </x-filament::button>
                                <x-filament::button
                                    color="danger"
                                    size="sm"
                                    variant="link"
                                    icon="heroicon-m-trash"
                                    wire:click="clearAllAmounts"
                                    wire:confirm="Are you sure you want to clear all amounts in this matrix?"
                                >
                                    Clear Matrix
                                </x-filament::button>
                                <x-filament::button
                                    color="success"
                                    size="sm"
                                    icon="heroicon-m-check"
                                    wire:click="saveMatrix"
                                >
                                    Save Matrix
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </x-slot>

                @if($selectedAcademicYear)
                    <div class="relative border rounded-lg overflow-x-auto">
                        <table class="w-full text-sm text-center border-collapse table-auto">
                            <thead class="sticky top-0 z-20 bg-gray-50 dark:bg-gray-800">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="sticky left-0 z-30 bg-gray-50 dark:bg-gray-800 px-2 py-3 font-bold text-gray-950 dark:text-white border-r border-gray-200 dark:border-gray-700 shadow-[2px_0_5px_rgba(0,0,0,0.05)] w-[180px] min-w-[180px] text-left whitespace-nowrap">
                                        Member Group
                                    </th>
                                    @foreach($months as $month)
                                        <th class="px-2 py-3 font-bold text-gray-950 dark:text-white border-r border-gray-200 dark:border-gray-700 min-w-[100px]">
                                            {{ $month }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($memberGroups as $group)
                                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-2 font-medium text-gray-950 dark:text-white border-r border-gray-200 dark:border-gray-700 shadow-[2px_0_5px_rgba(0,0,0,0.05)] whitespace-nowrap">
                                            {{ $group->name }}
                                        </td>
                                        @foreach($months as $month)
                                            <td class="px-2 py-2 border-r border-gray-200 dark:border-gray-700">
                                                <input
                                                    type="number"
                                                    wire:model.live="contributionAmounts.{{ $group->id }}.{{ $month }}"
                                                    class="w-full px-2 py-1 text-center border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                                                    step="0.01"
                                                    min="0"
                                                    max="999999.99"
                                                    placeholder="0.00"
                                                />
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-m-calendar-days" class="w-12 h-12 mx-auto mb-4 opacity-50" />
                        <p class="text-lg font-medium">Please select an academic year to view the contribution matrix.</p>
                        <p class="text-sm mt-2">Use the dropdown above to select an academic year.</p>
                    </div>
                @endif
            </x-filament::section>
        @else
            {{-- List View (Default Filament Table) --}}
            {{ $this->table }}
        @endif
    </div>
</x-filament-panels::page>
