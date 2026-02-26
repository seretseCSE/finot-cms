<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Period Selection Form -->
        <form wire:submit.prevent="generateStatement" class="bg-white rounded-lg shadow p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Financial Statement Generation</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{ $this->getFormSchema() }}
            </div>
            <div class="mt-6">
                {{ $this->getActions() }}
            </div>
        </form>

        <!-- Error Display -->
        @if($errors->has('generation_error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 mr-2" />
                    <div>
                        <h4 class="text-red-800 font-medium">Generation Error</h4>
                        <p class="text-red-600 text-sm">{{ $errors->get('generation_error') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
