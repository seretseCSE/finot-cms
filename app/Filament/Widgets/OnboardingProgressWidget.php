<?php

namespace App\Filament\Widgets;

use App\Models\ProductTourCompletion;
use Filament\Widgets\Widget;

class OnboardingProgressWidget extends Widget
{
    protected static ?int $sort = -5;

    protected static string $view = 'filament.widgets.onboarding-progress';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return config('product-tour.enabled') && $user->hasAnyRole(config('product-tour.supported_roles', []));
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $role = $user->roles->first()?->name;

        $completions = ProductTourCompletion::where('user_id', $user->id)
            ->where('role', $role)
            ->get()
            ->keyBy('tour_key');

        $tours = config('product-tour.tours', []);
        $stats = [];

        foreach ($tours as $key => $definition) {
            $roles = $definition['roles'] ?? [];
            if (!in_array($role, $roles)) continue;

            $completion = $completions->get($key);
            $stats[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'status' => $this->getStatus($completion),
                'progress' => $completion?->completion_percentage ?? 0,
            ];
        }

        $total = count($stats);
        $completed = count(array_filter($stats, fn ($s) => $s['status'] === 'completed'));
        $overallProgress = $total > 0 ? round(($completed / $total) * 100) : 0;

        return [
            'stats' => $stats,
            'totalTours' => $total,
            'completedTours' => $completed,
            'overallProgress' => $overallProgress,
        ];
    }

    protected function getStatus(?ProductTourCompletion $completion): string
    {
        if (!$completion) return 'not_started';
        if ($completion->isCompleted()) return 'completed';
        if ($completion->isSkipped()) return 'skipped';
        return 'in_progress';
    }
}
