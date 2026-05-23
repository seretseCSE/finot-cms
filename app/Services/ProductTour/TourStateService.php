<?php

namespace App\Services\ProductTour;

use App\Models\ProductTourCompletion;
use App\Models\User;

class TourStateService
{
    public function getProgress(User $user, string $tourKey, string $role, string $panel = 'admin'): ?ProductTourCompletion
    {
        return ProductTourCompletion::where('user_id', $user->id)
            ->where('tour_key', $tourKey)
            ->where('role', $role)
            ->where('panel', $panel)
            ->first();
    }

    public function saveProgress(
        User $user,
        string $tourKey,
        string $role,
        int $step,
        int $percentage,
        string $panel = 'admin',
        ?string $tourVersion = null,
        array $metadata = []
    ): ProductTourCompletion {
        $record = ProductTourCompletion::updateOrCreate(
            [
                'user_id' => $user->id,
                'tour_key' => $tourKey,
                'role' => $role,
                'panel' => $panel,
            ],
            [
                'tour_version' => $tourVersion ?? config('product-tour.current_version', '1.0.0'),
                'progress_step' => $step,
                'completion_percentage' => $percentage,
                'completed_at' => $percentage >= 100 ? now() : null,
                'metadata' => $metadata,
            ]
        );

        return $record;
    }

    public function markCompleted(
        User $user,
        string $tourKey,
        string $role,
        string $panel = 'admin',
        ?string $tourVersion = null
    ): ProductTourCompletion {
        return $this->saveProgress(
            $user, $tourKey, $role, 0, 100, $panel, $tourVersion,
            ['completed_at' => now()->toIso8601String()]
        );
    }

    public function markSkipped(
        User $user,
        string $tourKey,
        string $role,
        string $panel = 'admin'
    ): ProductTourCompletion {
        $record = $this->getOrCreate($user, $tourKey, $role, $panel);
        $record->update([
            'skipped_at' => now(),
        ]);

        return $record->fresh();
    }

    public function resume(User $user, string $tourKey, string $role, string $panel = 'admin'): ?array
    {
        $progress = $this->getProgress($user, $tourKey, $role, $panel);

        if (!$progress || $progress->isCompleted() || $progress->isSkipped()) {
            return null;
        }

        return [
            'step' => $progress->progress_step,
            'percentage' => $progress->completion_percentage,
            'tour_key' => $progress->tour_key,
        ];
    }

    public function reset(User $user, string $tourKey, ?string $role = null, string $panel = 'admin'): void
    {
        $query = ProductTourCompletion::where('user_id', $user->id)
            ->where('tour_key', $tourKey)
            ->where('panel', $panel);

        if ($role) {
            $query->where('role', $role);
        }

        $query->delete();
    }

    public function resetAllForUser(User $user, string $panel = 'admin'): void
    {
        ProductTourCompletion::where('user_id', $user->id)
            ->where('panel', $panel)
            ->delete();
    }

    public function resetAllForTour(string $tourKey, string $panel = 'admin'): void
    {
        ProductTourCompletion::where('tour_key', $tourKey)
            ->where('panel', $panel)
            ->delete();
    }

    public function invalidateVersion(string $version, string $panel = 'admin'): int
    {
        return ProductTourCompletion::where('panel', $panel)
            ->where('tour_version', '<>', $version)
            ->orWhereNull('tour_version')
            ->delete();
    }

    public function shouldShowTour(User $user, string $tourKey, string $role, string $currentVersion, string $panel = 'admin'): bool
    {
        $progress = $this->getProgress($user, $tourKey, $role, $panel);

        if (!$progress) {
            return true;
        }

        return $progress->shouldShowTour($currentVersion);
    }

    protected function getOrCreate(User $user, string $tourKey, string $role, string $panel = 'admin'): ProductTourCompletion
    {
        return ProductTourCompletion::firstOrCreate(
            [
                'user_id' => $user->id,
                'tour_key' => $tourKey,
                'role' => $role,
                'panel' => $panel,
            ],
            [
                'tour_version' => config('product-tour.current_version', '1.0.0'),
                'progress_step' => 0,
                'completion_percentage' => 0,
            ]
        );
    }
}
