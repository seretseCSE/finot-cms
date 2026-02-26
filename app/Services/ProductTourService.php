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
        $key = "tour_completed_{$role}_{$user->id}";
        return !Cache::has($key);
    }

    public function markTourCompleted(User $user, string $role): void
    {
        $key = "tour_completed_{$role}_{$user->id}";
        Cache::forever($key, true);
    }

    public function restartTour(User $user, string $role): void
    {
        $key = "tour_completed_{$role}_{$user->id}";
        Cache::forget($key);
    }

    public function getAllCompletedTours(User $user): array
    {
        $prefix = "tour_completed_";
        $keys = Cache::getRedis()->connection()->keys("*{$prefix}{$user->id}");
        
        $completed = [];
        foreach ($keys as $key) {
            $role = str_replace(["{$prefix}{$user->id}_"], '', $key);
            $completed[] = $role;
        }
        
        return $completed;
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
