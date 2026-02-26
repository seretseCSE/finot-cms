<?php

namespace App\Jobs;

use App\Models\Tour;
use App\Models\TourPassenger;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CancelTourNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Tour $tour,
        protected string $cancellationReason
    ) {}

    public function handle(): void
    {
        try {
            // Get all confirmed passengers who are linked members
            $confirmedPassengers = TourPassenger::where('tour_id', $this->tour->id)
                ->where('status', 'Confirmed')
                ->whereNotNull('member_id')
                ->with('member')
                ->get();

            foreach ($confirmedPassengers as $passenger) {
                // Create in-app notification for the linked member
                // This would depend on your notification system
                // $passenger->member->notifications()->create([
                //     'title' => 'Tour Cancelled',
                //     'message' => "The tour to {$this->tour->place} on {$this->tour->ethiopian_date} has been cancelled. Reason: {$this->cancellationReason}",
                //     'type' => 'tour_cancellation',
                //     'data' => [
                //         'tour_id' => $this->tour->id,
                //         'tour_place' => $this->tour->place,
                //         'cancellation_reason' => $this->cancellationReason,
                //     ],
                // ]);

                // For now, just log the notification
                Log::info('Tour cancellation notification created', [
                    'member_id' => $passenger->member_id,
                    'member_name' => $passenger->member->full_name,
                    'tour_id' => $this->tour->id,
                    'tour_place' => $this->tour->place,
                    'cancellation_reason' => $this->cancellation_reason,
                ]);
            }

            Log::info('Tour cancellation notifications sent', [
                'tour_id' => $this->tour->id,
                'tour_place' => $this->tour->place,
                'notifications_sent' => $confirmedPassengers->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send tour cancellation notifications', [
                'tour_id' => $this->tour->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }
}
