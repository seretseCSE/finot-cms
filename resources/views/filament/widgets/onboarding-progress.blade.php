<x-filament-widgets::widget>
    <div class="ft-onboarding-cards">
        <div class="ft-onboarding-card ft-onboarding-card--overall">
            <p class="ft-onboarding-card__kicker">Onboarding</p>
            <h3 class="ft-onboarding-card__title">Overall progress</h3>
            <p class="ft-onboarding-card__value">{{ $overallProgress }}%</p>
            <p class="ft-onboarding-card__meta">{{ $completedTours }} of {{ $totalTours }} tours completed</p>
            <div class="ft-onboarding-card__bar" aria-hidden="true">
                <span style="width: {{ $overallProgress }}%"></span>
            </div>
        </div>

        @forelse ($stats as $stat)
            @php
                $action = $stat['status'] === 'completed' ? 'Replay' : ($stat['status'] === 'in_progress' ? 'Continue' : 'Start');
                $statusLabel = match ($stat['status']) {
                    'completed' => 'Completed',
                    'in_progress' => 'In progress',
                    'skipped' => 'Skipped',
                    default => 'Not started',
                };
            @endphp
            <div class="ft-onboarding-card ft-onboarding-card--{{ $stat['status'] }}">
                <p class="ft-onboarding-card__kicker">{{ $statusLabel }}</p>
                <h3 class="ft-onboarding-card__title">{{ $stat['label'] }}</h3>
                @if (filled($stat['description']))
                    <p class="ft-onboarding-card__meta">{{ $stat['description'] }}</p>
                @endif
                <button
                    type="button"
                    data-restart-tour="{{ $stat['key'] }}"
                    class="ft-onboarding-card__action"
                >
                    {{ $action }}
                </button>
            </div>
        @empty
            <div class="ft-onboarding-card">
                <h3 class="ft-onboarding-card__title">No tours</h3>
                <p class="ft-onboarding-card__meta">No tours are available for your role.</p>
            </div>
        @endforelse
    </div>
</x-filament-widgets::widget>
