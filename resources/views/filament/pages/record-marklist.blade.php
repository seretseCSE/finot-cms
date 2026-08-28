<x-filament-panels::page>
    <div class="space-y-6" data-tour="record-marklist">
        <form wire:submit="loadRoster" class="grid gap-4 md:grid-cols-4 items-end">
            <div>
                <label class="text-sm font-medium">Class</label>
                <select wire:model="classId" class="w-full rounded-lg border-gray-300 dark:bg-gray-800">
                    <option value="">Select class</option>
                    @foreach($classes as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Term</label>
                <select wire:model="termId" class="w-full rounded-lg border-gray-300 dark:bg-gray-800">
                    <option value="">Select term</option>
                    @foreach($terms as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Subject</label>
                <select wire:model="subjectId" class="w-full rounded-lg border-gray-300 dark:bg-gray-800">
                    <option value="">Select subject</option>
                    @foreach($subjects as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-filament::button type="submit">Load roster</x-filament::button>
            </div>
        </form>

        @if(count($this->rows))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left p-2">Student</th>
                            <th class="p-2">Conduct</th>
                            <th class="p-2">Memorization</th>
                            <th class="p-2">Participation</th>
                            <th class="p-2">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->rows as $index => $row)
                            <tr class="border-t">
                                <td class="p-2">{{ $row['name'] }}</td>
                                @foreach(['conduct','memorization','participation'] as $field)
                                    <td class="p-2">
                                        <select wire:model="rows.{{ $index }}.{{ $field }}" class="rounded border-gray-300 dark:bg-gray-800">
                                            <option value="">—</option>
                                            @foreach($rubric as $score)
                                                <option value="{{ $score->value }}">{{ str_replace('_', ' ', $score->value) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td class="p-2">
                                    <input type="text" wire:model="rows.{{ $index }}.remarks" class="w-full rounded border-gray-300 dark:bg-gray-800">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex gap-3">
                <x-filament::button wire:click="saveDraft" color="gray">Save draft</x-filament::button>
                <x-filament::button wire:click="submit">Submit for approval</x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
