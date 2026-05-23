<?php

namespace App\Services\ProductTour;

use App\Models\ProductTourAnalytic;
use Illuminate\Support\Collection;

class FeatureDiscoveryService
{
    protected TourRegistry $registry;

    protected TourStateService $stateService;

    protected TourAnalyticsService $analytics;

    public function __construct(TourRegistry $registry, TourStateService $stateService, TourAnalyticsService $analytics)
    {
        $this->registry = $registry;
        $this->stateService = $stateService;
        $this->analytics = $analytics;
    }

    public function discover(User $user, string $panel = 'admin'): Collection
    {
        if (!config('product-tour.feature_flags.feature_discovery') ||
            !config('product-tour.feature_discovery.enabled')) {
            return collect();
        }

        $role = $user->roles->first()?->name;
        if (!$role) {
            return collect();
        }

        $currentVersion = config('product-tour.current_version', '1.0.0');
        $tours = $this->registry->forRole($role);
        $discoveries = collect();

        foreach ($tours as $key => $definition) {
            $shouldShow = $this->stateService->shouldShowTour($user, $key, $role, $currentVersion, $panel);

            if ($shouldShow && $this->isNewOrUpdated($user, $key, $currentVersion, $panel)) {
                $discoveries->push([
                    'tour_key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'version' => $definition['version'],
                    'auto_start' => $definition['auto_start'] ?? false,
                ]);
            }
        }

        return $discoveries;
    }

    public function getHints(User $user, string $currentPage, string $panel = 'admin'): Collection
    {
        if (!config('product-tour.feature_flags.contextual_hints')) {
            return collect();
        }

        $maxHints = config('product-tour.feature_discovery.max_hints_per_session', 3);
        $role = $user->roles->first()?->name;
        if (!$role) {
            return collect();
        }

        $tours = $this->registry->forRoleAndPage($role, $currentPage);
        $hints = collect();

        foreach ($tours as $key => $definition) {
            if ($hints->count() >= $maxHints) {
                break;
            }

            if (!$this->wasHintDismissedRecently($user, $key)) {
                $hints->push([
                    'tour_key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ]);
            }
        }

        return $hints;
    }

    public function dismissHint(User $user, string $tourKey): void
    {
        $this->analytics->track('hint_dismissed', $tourKey, [
            'metadata' => ['dismissed_at' => now()->toIso8601String()],
        ]);
    }

    protected function isNewOrUpdated(User $user, string $tourKey, string $currentVersion, string $panel): bool
    {
        $progress = $this->stateService->getProgress($user, $tourKey, $user->roles->first()?->name, $panel);

        if (!$progress) {
            return true;
        }

        return $progress->tour_version !== $currentVersion;
    }

    protected function wasHintDismissedRecently(User $user, string $tourKey): bool
    {
        $dismissDays = config('product-tour.feature_discovery.dismiss_for_days', 7);

        $recentDismissal = ProductTourAnalytic::where('user_id', $user->id)
            ->where('tour_key', $tourKey)
            ->where('event_type', 'hint_dismissed')
            ->where('created_at', '>=', now()->subDays($dismissDays))
            ->exists();

        return $recentDismissal;
    }
}
