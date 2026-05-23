<?php

namespace App\Services\ProductTour;

use App\Models\User;
use Illuminate\Support\Collection;

class ProductTourService
{
    protected TourRegistry $registry;

    protected TourAnalyticsService $analytics;

    protected TourStateService $state;

    protected FeatureDiscoveryService $featureDiscovery;

    public function __construct(
        TourRegistry $registry,
        TourAnalyticsService $analytics,
        TourStateService $state,
        FeatureDiscoveryService $featureDiscovery
    ) {
        $this->registry = $registry;
        $this->analytics = $analytics;
        $this->state = $state;
        $this->featureDiscovery = $featureDiscovery;
    }

    public function isAvailable(?User $user = null, string $panel = 'admin'): bool
    {
        if (!config('product-tour.enabled')) {
            return false;
        }

        if (in_array(app()->environment(), config('product-tour.excluded_environments', []))) {
            return false;
        }

        if (!$user) {
            $user = auth()->user();
        }

        if (!$user) {
            return false;
        }

        if (!in_array($panel, config('product-tour.panels', []))) {
            return false;
        }

        return true;
    }

    public static function isAvailableStatic(?User $user = null, string $panel = 'admin'): bool
    {
        return app(self::class)->isAvailable($user, $panel);
    }

    public function status(User $user, string $panel = 'admin'): array
    {
        if (!$this->isAvailable($user, $panel)) {
            return [
                'available' => false,
                'tours' => [],
                'feature_discoveries' => [],
                'hints' => [],
            ];
        }

        $role = $user->roles->first()?->name;
        $currentVersion = config('product-tour.current_version', '1.0.0');
        $tours = [];
        $allDefinitions = $this->registry->forRole($role ?? '');

        foreach ($allDefinitions as $key => $definition) {
            $pages = $definition['pages'] ?? [];
            $progress = $this->state->getProgress($user, $key, $role ?? '', $panel);

            $tours[] = [
                'tour_key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'version' => $definition['version'],
                'pages' => $pages,
                'auto_start' => $definition['auto_start'] ?? false,
                'allow_skip' => $definition['allow_skip'] ?? true,
                'allow_resume' => $definition['allow_resume'] ?? false,
                'show_progress' => $definition['show_progress'] ?? true,
                'status' => $this->getTourStatus($progress, $currentVersion),
                'progress_step' => $progress?->progress_step ?? 0,
                'completion_percentage' => $progress?->completion_percentage ?? 0,
            ];
        }

        $currentPage = request()->segment(2) ?? 'dashboard';
        $pageTours = $this->registry->forRoleAndPage($role ?? '', $currentPage);

        return [
            'available' => true,
            'current_page' => $currentPage,
            'current_version' => $currentVersion,
            'tours' => $tours,
            'page_tours' => array_keys($pageTours),
            'feature_discoveries' => $this->featureDiscovery->discover($user, $panel),
            'hints' => $this->featureDiscovery->getHints($user, $currentPage, $panel),
            'auto_start_tour' => $this->getAutoStartTour($tours, $currentPage),
        ];
    }

    public function start(string $tourKey, User $user, string $panel = 'admin'): array
    {
        $tour = $this->registry->get($tourKey);
        if (!$tour) {
            abort(404, "Tour '{$tourKey}' not found.");
        }

        $this->validateAccess($tour, $user);

        $this->analytics->trackStarted($tourKey, [
            'panel' => $panel,
            'page' => request()->path(),
        ]);

        $role = $user->roles->first()?->name;
        $resume = $this->state->resume($user, $tourKey, $role ?? '', $panel);

        return [
            'tour_key' => $tourKey,
            'definition' => $tour,
            'resume' => $resume,
        ];
    }

    public function complete(string $tourKey, User $user, string $panel = 'admin', array $metadata = []): void
    {
        $tour = $this->registry->get($tourKey);
        if (!$tour) {
            abort(404, "Tour '{$tourKey}' not found.");
        }

        $this->validateAccess($tour, $user);

        $role = $user->roles->first()?->name;
        $this->state->markCompleted($user, $tourKey, $role ?? '', $panel, $tour['version']);
        $this->analytics->trackCompleted($tourKey, [
            'panel' => $panel,
            'page' => request()->path(),
            'metadata' => $metadata,
        ]);
    }

    public function skip(string $tourKey, User $user, string $panel = 'admin'): void
    {
        $tour = $this->registry->get($tourKey);
        if (!$tour) {
            abort(404, "Tour '{$tourKey}' not found.");
        }

        $this->validateAccess($tour, $user);

        $role = $user->roles->first()?->name;
        $this->state->markSkipped($user, $tourKey, $role ?? '', $panel);
        $this->analytics->trackSkipped($tourKey, [
            'panel' => $panel,
            'page' => request()->path(),
        ]);
    }

    public function restart(string $tourKey, User $user, string $panel = 'admin'): void
    {
        $tour = $this->registry->get($tourKey);
        if (!$tour) {
            abort(404, "Tour '{$tourKey}' not found.");
        }

        $this->validateAccess($tour, $user);

        $role = $user->roles->first()?->name;
        $this->state->reset($user, $tourKey, $role, $panel);
        $this->analytics->trackRestarted($tourKey, [
            'panel' => $panel,
            'page' => request()->path(),
        ]);
    }

    public function saveProgress(string $tourKey, int $step, int $percentage, User $user, string $panel = 'admin'): void
    {
        $tour = $this->registry->get($tourKey);
        if (!$tour) {
            abort(404, "Tour '{$tourKey}' not found.");
        }

        $role = $user->roles->first()?->name;
        $this->state->saveProgress(
            $user, $tourKey, $role ?? '', $step, $percentage, $panel, $tour['version'] ?? null
        );
    }

    public function getToursForUser(User $user, string $panel = 'admin'): array
    {
        $role = $user->roles->first()?->name;
        if (!$role) {
            return [];
        }

        return $this->registry->forRole($role);
    }

    public function getSteps(string $tourKey, User $user): array
    {
        $tour = $this->registry->get($tourKey);
        if (!$tour) {
            return [];
        }

        $this->validateAccess($tour, $user);

        return $tour['steps'] ?? [];
    }

    protected function getTourStatus(?ProductTourCompletion $progress, string $currentVersion): string
    {
        if (!$progress) {
            return 'new';
        }
        if ($progress->isSkipped()) {
            return 'skipped';
        }
        if ($progress->isCompleted()) {
            return $progress->tour_version === $currentVersion ? 'completed' : 'updated';
        }

        return 'in_progress';
    }

    protected function getAutoStartTour(array $tours, string $currentPage): ?string
    {
        if (!config('product-tour.auto_start.enabled')) {
            return null;
        }

        foreach ($tours as $tour) {
            if ($tour['auto_start'] && $tour['status'] === 'new') {
                if (in_array($currentPage, $tour['pages'])) {
                    return $tour['tour_key'];
                }
            }
        }

        return null;
    }

    protected function validateAccess(array $tour, User $user): void
    {
        $roles = $tour['roles'] ?? [];
        $userRole = $user->roles->first()?->name;

        if (!empty($roles) && !in_array($userRole, $roles)) {
            abort(403, 'You do not have access to this tour.');
        }
    }
}
