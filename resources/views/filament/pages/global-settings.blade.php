<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Global Settings</h2>
            <p class="text-gray-600 mb-6">Manage your application's global configuration settings.</p>
            
            {{ $this->form }}
            
            <div class="mt-6">
                <x-filament::button wire:click="save" type="submit">
                    <x-filament::icon icon="heroicon-o-check" class="w-4 h-4 mr-2" />
                    Save Settings
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
