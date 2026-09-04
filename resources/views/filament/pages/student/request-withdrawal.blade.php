<x-filament-panels::page>
    <div class="mx-auto w-full max-w-xl" data-tour="student-withdrawal">
        @php
            $existing = $this->existing();
            $enrollment = $this->enrollment();
        @endphp

        <div class="rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-6 sm:p-8">
            <div class="mb-6">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Leave your class') }}</h3>
                <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    {{ __('Explain why you want to withdraw. Education Head reviews the request; HR finishes it if approved.') }}
                </p>
            </div>

            @if ($existing)
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ __('Current status') }}:
                    <strong>{{ $existing->status->value ?? $existing->status }}</strong>
                    <a href="{{ route('withdrawals.print', $existing) }}" class="ml-2 font-semibold underline">{{ __('Print') }}</a>
                </div>
            @endif

            @if ($enrollment)
                <div class="space-y-5">
                    {{ $this->form }}
                    <x-filament::button wire:click="submit" color="primary">
                        {{ __('Submit request') }}
                    </x-filament::button>
                </div>
            @else
                @include('filament.pages.student.partials.empty-state', [
                    'icon' => 'heroicon-o-user-minus',
                    'title' => __('No active enrollment'),
                    'description' => __('You can only request withdrawal while you are enrolled in a class.'),
                ])
            @endif
        </div>
    </div>
</x-filament-panels::page>
