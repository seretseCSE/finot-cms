<?php

namespace App\Observers;

use App\Models\Tour;

class TourObserver
{
    /**
     * Handle the Tour "force deleting" event.
     */
    public function forceDeleting(Tour $tour): void
    {
        // Cancel all passengers before force delete
        $tour->passengers()->delete();

        // Delete attendance sessions and records
        $tour->attendanceSessions()->delete();
    }

    /**
     * Handle the Tour "deleted" event (soft delete).
     */
    public function deleted(Tour $tour): void
    {
        if ($tour->isForceDeleting()) {
            return;
        }

        // Optionally cancel confirmed passengers when a tour is soft-deleted
        $tour->passengers()
            ->where('status', 'Confirmed')
            ->update([
                'status' => 'Cancelled',
                'cancellation_reason' => 'Tour removed',
            ]);
    }
}
