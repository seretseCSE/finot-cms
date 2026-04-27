<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductTourService
{
    public function shouldShowPwaPrompt(User $user): bool
    {
        $visitCount = $this->getVisitCount($user);

        return $visitCount === 3;
    }

    public function shouldShowTour(User $user, string $role): bool
    {
        $completed = $this->getAllCompletedTours($user);

        return ! in_array($role, $completed);
    }

    public function markTourCompleted(User $user, string $role): void
    {
        $completed = $this->getAllCompletedTours($user);

        if (! in_array($role, $completed)) {
            $completed[] = $role;
            Cache::forever($this->getCompletedToursKey($user), $completed);
        }
    }

    public function restartTour(User $user, string $role): void
    {
        $completed = $this->getAllCompletedTours($user);
        $completed = array_values(array_diff($completed, [$role]));

        if (empty($completed)) {
            Cache::forget($this->getCompletedToursKey($user));
        } else {
            Cache::forever($this->getCompletedToursKey($user), $completed);
        }
    }

    public function getAllCompletedTours(User $user): array
    {
        return Cache::get($this->getCompletedToursKey($user), []);
    }

    private function getCompletedToursKey(User $user): string
    {
        return "tour_completed_all_{$user->id}";
    }

    private function getVisitCount(User $user): int
    {
        return Cache::remember("visit_count_{$user->id}", 3600, function () use ($user) {
            return DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->count();
        });
    }

    public function incrementVisitCount(User $user): void
    {
        Cache::increment("visit_count_{$user->id}");
    }
}
