<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="grid grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500 uppercase">Total Items</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ $this->table->getRecords()->count() }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500 uppercase">Active Items</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ $this->table->getRecords()->where('status', 'Active')->count() }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="text-sm text-gray-500 uppercase">Low Stock</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ $this->table->getRecords()->filter(fn ($i) => $i->current_stock < 5)->count() }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500 uppercase">Total Value</div>
                <div class="text-2xl font-bold text-gray-900">
                    ETB {{ number_format($this->table->getRecords()->sum(fn ($i) => $i->purchase_price * $i->current_stock), 2) }}
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
