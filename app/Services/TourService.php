<?php

namespace App\Services;

use App\Models\Tour;
use Illuminate\Support\Facades\Log;

class TourService
{
    /**
     * Log tour creation to audit trail.
     *
     * @param Tour $tour The created tour
     * @return void
     */
    public function logTourCreation(Tour $tour): void
    {
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_created',
            'entity_id' => $tour->id,
            'entity_type' => 'tour',
            'old_value' => null,
            'new_value' => json_encode([
                'place' => $tour->place,
                'tour_date' => $tour->tour_date,
                'start_time' => $tour->start_time,
            ]),
            'user_id' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
