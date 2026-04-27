<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-8 flex gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <x-filament::loading-indicator wire:loading wire:target="save" />
                Save Changes
            </x-filament::button>
            
            <a href="/admin" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium transition-colors border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 text-gray-700 bg-white dark:border-gray-600 dark:text-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700">
                Cancel
            </a>
        </div>
    </form>
</x-filament-panels::page>
