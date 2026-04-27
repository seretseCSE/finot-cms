<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500 uppercase">Stock In</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ $this->table->getRecords()->where('movement_type', 'Stock In')->sum('quantity') }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="text-sm text-gray-500 uppercase">Stock Out</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ $this->table->getRecords()->where('movement_type', 'Stock Out')->sum('quantity') }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500 uppercase">Total Movements</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ $this->table->getRecords()->count() }}
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
