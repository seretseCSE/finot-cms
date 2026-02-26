<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Member Code</h3>
                    <p class="text-lg font-semibold">{{ $record->member_code }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Full Name</h3>
                    <p class="text-lg font-semibold">{{ $record->full_name }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Current Group</h3>
                    <p class="text-lg font-semibold">{{ $record->currentGroup?->name ?? 'Unassigned' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">Group History</h2>
            </div>
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
