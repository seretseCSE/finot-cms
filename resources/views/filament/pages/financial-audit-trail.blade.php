<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Financial Audit Trail</h3>
                    <span class="text-sm text-gray-500">
                        Financial events only
                    </span>
                </div>
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
