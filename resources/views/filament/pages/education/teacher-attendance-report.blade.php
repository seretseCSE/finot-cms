<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Report Filters</h3>
            {{ $this->form }}
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
