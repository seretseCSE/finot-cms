<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Bulk Student Promotion Wizard</h2>
            
            {{ $this->form }}
            
            <div class="mt-6 flex justify-between">
                <div class="text-sm text-gray-600">
                    <x-filament::icon icon="heroicono-information-circle" class="w-4 h-4 inline mr-1" />
                    This wizard will promote students to their next academic year/class
                </div>
                
                @if($this->currentStep === 3)
                    <x-filament::button 
                        wire:click="promoteStudents" 
                        wire:loading.attr="disabled"
                        color="primary"
                    >
                        <x-filament::loading-indicator class="mr-2" />
                        Promote Students
                    </x-filament::button>
                @endif
            </div>
        </div>

        <!-- Students Preview Table -->
        @if($this->wizardData['from_class_id'] ?? null)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold mb-4">Students in Selected Class</h3>
                {{ $this->table }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
