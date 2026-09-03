<x-filament-panels::page>
    <div class="space-y-6 max-w-2xl" data-tour="student-withdrawal">
        @php
            $existing = $this->existing();
            $enrollment = $this->enrollment();
        @endphp

        @if ($existing)
            <p class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('Current status') }}: <strong>{{ $existing->status->value ?? $existing->status }}</strong>
                <a href="{{ route('withdrawals.print', $existing) }}" class="underline ml-2">{{ __('Print') }}</a>
            </p>
        @endif

        @if ($enrollment)
            {{ $this->form }}

            <x-filament::button wire:click="submit" color="primary">
                {{ __('Submit') }}
            </x-filament::button>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('You do not have an active enrollment.') }}</p>
        @endif
    </div>
</x-filament-panels::page>
