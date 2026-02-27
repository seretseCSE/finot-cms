<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">System Backup & Restore</h2>
            <p class="text-gray-600 mb-6">Manage system backups and restore points. Create full database and file backups.</p>
            
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
