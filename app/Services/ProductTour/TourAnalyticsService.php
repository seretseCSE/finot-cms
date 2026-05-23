<?php

namespace App\Services\ProductTour;

use App\Models\ProductTourAnalytic;
use Illuminate\Support\Facades\DB;

class TourAnalyticsService
{
    public function track(string $eventType, string $tourKey, array $data = []): ProductTourAnalytic
    {
        if (!config('product-tour.analytics.enabled')) {
            return new ProductTourAnalytic();
        }

        return ProductTourAnalytic::record($eventType, $tourKey, $data);
    }

    public function trackStarted(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('started', $tourKey, $data);
    }

    public function trackCompleted(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('completed', $tourKey, $data);
    }

    public function trackSkipped(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('skipped', $tourKey, $data);
    }

    public function trackAbandoned(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('abandoned', $tourKey, $data);
    }

    public function trackResumed(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('resumed', $tourKey, $data);
    }

    public function trackRestarted(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('restarted', $tourKey, $data);
    }

    public function trackStepChanged(string $tourKey, string $stepKey, array $data = []): ProductTourAnalytic
    {
        $data['step_key'] = $stepKey;
        return $this->track('step_changed', $tourKey, $data);
    }

    public function trackFailed(string $tourKey, array $data = []): ProductTourAnalytic
    {
        return $this->track('failed', $tourKey, $data);
    }

    public function completionRate(?string $tourKey = null): float
    {
        $query = ProductTourAnalytic::query();

        if ($tourKey) {
            $query->forTour($tourKey);
        }

        $started = (clone $query)->byEvent('started')->count();
        $completed = (clone $query)->byEvent('completed')->count();

        if ($started === 0) {
            return 0.0;
        }

        return round(($completed / $started) * 100, 2);
    }

    public function abandonedRate(?string $tourKey = null): float
    {
        $query = ProductTourAnalytic::query();

        if ($tourKey) {
            $query->forTour($tourKey);
        }

        $started = (clone $query)->byEvent('started')->count();
        $abandoned = (clone $query)->byEvent('abandoned')->count();

        if ($started === 0) {
            return 0.0;
        }

        return round(($abandoned / $started) * 100, 2);
    }

    public function mostSkippedStep(string $tourKey): ?string
    {
        return ProductTourAnalytic::forTour($tourKey)
            ->byEvent('skipped')
            ->select('step_key', DB::raw('COUNT(*) as count'))
            ->groupBy('step_key')
            ->orderByDesc('count')
            ->value('step_key');
    }

    public function averageCompletionTime(string $tourKey): ?float
    {
        return ProductTourAnalytic::forTour($tourKey)
            ->byEvent('completed')
            ->whereNotNull('metadata->duration_ms')
            ->avg(DB::raw("JSON_EXTRACT(metadata, '$.duration_ms')"));
    }

    public function stats(?string $tourKey = null): array
    {
        $query = ProductTourAnalytic::query();

        if ($tourKey) {
            $query->forTour($tourKey);
        }

        return [
            'started' => (clone $query)->byEvent('started')->count(),
            'completed' => (clone $query)->byEvent('completed')->count(),
            'skipped' => (clone $query)->byEvent('skipped')->count(),
            'abandoned' => (clone $query)->byEvent('abandoned')->count(),
            'resumed' => (clone $query)->byEvent('resumed')->count(),
            'failed' => (clone $query)->byEvent('failed')->count(),
            'completion_rate' => $this->completionRate($tourKey),
            'abandoned_rate' => $this->abandonedRate($tourKey),
        ];
    }
}
